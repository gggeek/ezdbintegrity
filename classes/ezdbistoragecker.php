<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * @todo allow to report on:
 * - number of OK files
 * - total size of files for both OK and orphan cases
 */
class ezdbiStorageChecker extends ezdbiBaseChecker
{
    /** @var eZDBInterface $db */
    protected $db;

    public function __construct( $dsn='' )
    {
        if ( $dsn == '' )
        {
            $db = eZDB::instance();
        }
        else
        {
            throw new Exception( "Custom db connection unsupported for now" );
        }
        $this->db = $db;
    }

    public function getChecks()
    {
        return array(
            'Images' => 'checks for any image file in the storage dir which are not in the ezimage table',
            'Files' => 'checks for any binary file in the storage dir which are not in the ezmedia or ezbinaryfile tables',
        );
    }

    public function check( $doDelete=false, $returnData=false )
    {
        return array(
            'Images' => $this->checkImages( $doDelete, $returnData ),
            'Files' => $this->checkFiles( $doDelete, $returnData ),
        );
    }

    /**
     * @todo optimize db query: use a prepared statement
     */
    public function checkImages( $doDelete=false, $returnData=false )
    {
        $violations = array(
            'violatingFileCount' => 0,
        );

        $ini = eZINI::instance( 'image.ini' );
        $pDir = $this->clusterizeDir( eZSys::storageDirectory() . '/' . $ini->variable( 'FileSettings', 'PublishedImages' ) );
        $vDir = $this->clusterizeDir( eZSys::storageDirectory() . '/' . $ini->variable( 'FileSettings', 'VersionedImages' ) );
        $dirs = array();
        // note: 'realpath' not to be used here
        if ( is_dir( $pDir ) )
        {
            $dirs[] = $pDir;
        }
        if ( is_dir( $vDir ) )
        {
            $dirs[] = $vDir;
        }

        foreach( $dirs as $dir )
        {
            $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ) );
            foreach ($files as $storageFileName => $storageFileInfo )
            {
                if ( $storageFileInfo->isDir() )
                {
                    continue;
                }

                $logicalFileName = $this->declusterizeFile($storageFileName);

                $sql =
                    "SELECT COUNT(*) AS found FROM ezimagefile " .
                    "WHERE filepath = '" . $this->db->escapeString($logicalFileName) . "'";
                $results = $this->db->arrayQuery($sql);

                if ( $results[0]['found'] == 0 )
                {
                    $violations['violatingFileCount']++;
                    if ( $returnData )
                    {
                        $violations['violatingFiles'][] = $storageFileName;
                    }

                    if ($doDelete)
                    {
                        unlink($storageFileName);
                    }
                }
                /*else
                {
                    echo "OK: $storageFileName\n";
                }*/
            }
        }

        return $violations;
    }

    /**
     * @todo optimize db query: use a prepared statement
     */
    public function checkFiles( $doDelete=false, $returnData=false )
    {
        $violations = array(
            'violatingFileCount' => 0,
        );

        $dir = $this->clusterizeDir( eZSys::storageDirectory() . '/original' );
        if ( !is_dir( $dir ) )
        {
            return $violations;
        }
        $dir = realpath( $dir );

        foreach ( glob( $dir . '/*', GLOB_ONLYDIR ) as $storageDir )
        {
            if ( in_array(basename($storageDir), array( '.', '..' ) ) )
            {
                continue;
            }

            foreach ( glob( $storageDir . '/*') as $storageFile )
            {
                if ( !is_file( $storageFile ) )
                {
                    continue;
                }

                $fileName = basename( $storageFile );
                $dirName = basename( $storageDir );

                $sql1 =
                    "SELECT COUNT(*) AS found FROM ezbinaryfile " .
                    "WHERE filename = '" . $this->db->escapeString( $fileName ) . "' AND mime_type LIKE '" . $this->db->escapeString( $dirName ) . "/%'";
                $sql2 =
                    "SELECT COUNT(*) AS found FROM ezmedia " .
                    "WHERE filename = '" . $this->db->escapeString( $fileName ) . "' AND mime_type LIKE '" . $this->db->escapeString( $dirName ) . "/%'";
                $results1 = $this->db->arrayQuery( $sql1 );
                $results2 = $this->db->arrayQuery( $sql2 );

                if ( $results1[0]['found'] == 0 && $results2[0]['found'] == 0 )
                {
                    $violations['violatingFileCount']++;
                    if ( $returnData )
                    {
                        $violations['violatingFiles'][] = $storageFile;
                    }

                    if ($doDelete)
                    {
                        unlink($storageFile);
                    }
                }
                /*else
                {
                    echo "OK: $storageFile\n";
                }*/
            }
        }

        return $violations;
    }

    protected function clusterizeDir( $dir )
    {
        $ini = eZINI::instance( 'file.ini');

        switch( $ini->variable( 'ClusteringSettings', 'FileHandler') )
        {
            case 'eZFSFileHandler':
            case 'eZFS2FileHandler':
                return $dir;

            case 'eZDBFileHandler':
                throw new \Exception( "The cluster file handler stores all data in the database. You should be safe by just removing the storage directory" );

            case 'eZDFSFileHandler':
                $nfsVar = $ini->variable( 'eZDFSClusteringSettings', 'MountPointPath' );
                if ( substr( $nfsVar, -1 ) != '/' )
                    $nfsVar = "$nfsVar/";
                return $nfsVar . $dir;

            default:
                throw new \Exception( "The cluster file handler in use is unsupported, data produced might be unreliable" );
        }
    }

    protected function deClusterizeFile( $filename )
    {
        $ini = eZINI::instance( 'file.ini');
        $nfsVar = $ini->variable( 'eZDFSClusteringSettings', 'MountPointPath' );
        if ( substr( $nfsVar, -1 ) != '/' )
            $nfsVar = "$nfsVar/";
        return preg_replace( "#^$nfsVar#", '', $filename );
    }
}
