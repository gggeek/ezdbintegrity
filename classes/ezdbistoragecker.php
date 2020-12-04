<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2020
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

    protected $checks = array(
        'Images' => 'checks for any image file in the storage dir which are not in the ezimage table',
        'Files' => 'checks for any binary file in the storage dir which are not in the ezmedia or ezbinaryfile tables',
        'ImagesAliases' => 'checks for any image alias file in the _aliases dir whithout original image file',
    );

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

    /**
     * @return array
     */
    public function getChecks()
    {
        return $this->checks;
    }

    public function checkAll( $doDelete=false, $returnData=false )
    {
        $out = array();
        foreach( array_keys( $this->checks ) as $check )
        {
            $out[$check] = $this->cehck($check);
        }
        return $out;
    }

    public function check( $type, $doDelete=false, $returnData=false )
    {
        switch( $type )
        {
            case 'Images':
                return $this->checkImages( $doDelete, $returnData );
            case 'Files':
                return $this->checkFiles( $doDelete, $returnData );
            case 'ImagesAliases':
                return $this->checkImageAliases( $doDelete, $returnData );
            default:
                throw new \Exception( "Unsupported type: '$type'" );
        }
    }

    /**
     * @todo optimize db query: use a prepared statement
     */
    public function checkImages( $doDelete=false, $returnData=false )
    {
        $violations = array();

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
                if ( $storageFileInfo->isDir() || strpos( $storageFileName, $pDir . '/_aliases/' ) === 0 )
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
                    if ( isset( $violations['violatingFileCount'] ) )
                    {
                        $violations['violatingFileCount']++;
                    }
                    else
                    {
                        $violations['violatingFileCount'] = 1;
                    }

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
        $violations = array();

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
                    if ( isset( $violations['violatingFileCount'] ) )
                    {
                        $violations['violatingFileCount']++;
                    }
                    else
                    {
                        $violations['violatingFileCount'] = 1;
                    }

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

    public function checkImageAliases( $doDelete = false, $returnData = false )
    {
        $violations = array();

        $ini = eZINI::instance( 'image.ini' );
        $pDir = $this->clusterizeDir( eZSys::storageDirectory() . '/' . $ini->variable( 'FileSettings', 'PublishedImages' ) );
        $aliasDir = realpath( $pDir . '/_aliases' );

        if ( is_dir( $aliasDir ) ) {
            foreach ( glob( $aliasDir . '/*' ) as $aliasNameDir )
            {
                if ( is_file( $aliasNameDir ) )
                {
                    continue;
                }

                $aliasName = basename( $aliasNameDir );
                $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $aliasNameDir ) );

                foreach ( $files as $aliasFileName => $aliasFileInfo )
                {
                    if ( $aliasFileInfo->isDir() )
                    {
                        continue;
                    }

                    $originalFileName = str_replace( '/_aliases/' . $aliasName, '', $aliasFileName );

                    if ( !file_exists( $originalFileName ) )
                    {
                        if ( isset( $violations['violatingFileCount'] ) )
                        {
                            $violations['violatingFileCount']++;
                        }
                        else
                        {
                            $violations['violatingFileCount'] = 1;
                        }

                        if ( $returnData )
                        {
                            $violations['violatingFiles'][] = $aliasFileName;
                        }

                        if ($doDelete)
                        {
                            unlink($aliasFileName);
                        }
                    }
                }
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
