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
        $pDir = eZSys::storageDirectory() . '/' . $ini->variable( 'FileSettings', 'PublishedImages' );
        $vDir = eZSys::storageDirectory() . '/' . $ini->variable( 'FileSettings', 'VersionedImages' );
        $dirs = array();
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

                $sql =
                    "SELECT COUNT(*) AS found FROM ezimagefile " .
                    "WHERE filepath = '" . $this->db->escapeString($storageFileName) . "'";
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

        foreach ( glob( eZSys::storageDirectory() . '/original/*', GLOB_ONLYDIR ) as $storageDir )
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
}
