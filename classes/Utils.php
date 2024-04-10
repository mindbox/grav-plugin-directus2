<?php
namespace Grav\Plugin\Directus2;

use Grav\Common\Grav;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Utils
{
    protected $lockfile = 'user/data/.directus2lock';
    protected $tempDir = 'tmp/directus/';
    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var array
     */
    protected $config;

    public function __construct( Grav $grav, array $config )
    {
        $this->grav = $grav;
        $this->config = $config;
    }

    /*
     * Logging
     */
    public function log( $message, $data = null ): void
    {
        $filename = 'logs/directus_' . date('Y-m-d') . '.log';

        $logText =  '[' . date( 'Y-m-d H:i:s', time() ) . '] ' . $message . "\n";
        if ( $data )
        {
            $logText .= $data . "\n";
        }

        if ( $this->config['logging'] )
        {
            file_put_contents( $filename, $logText, FILE_APPEND );
        }
    }

    /*
     * Signaling a process is already running to prevent interference
     */
    public function setLock(): void
    {
        // set lock file
        $this->log( 'locked' );
        touch( $this->lockfile );
    }
    /*
     * Singnaling the process is not running
     */
    public function unLock(): void
    {
        // remove lock file
        if ( file_exists( $this->lockfile ) )
        {
            unlink( $this->lockfile );
        }
        $this->log( 'unlocked' );
    }

    /*
     * checking process status
     */
    public function checkLock(): void
    {
        if ( file_exists( $this->lockfile ) )
        {
            if ( time() - filemtime( $this->lockfile ) > ( $this->config['lockfileLifetime'] ?? 120 ) )
            {
                unlink( $this->lockfile );
                $this->log( 'unlocked (lockfile too old)' );
            }
            else
            {
                $this->respond( 403, 'locked' );
                $this->log( 'still locked' );
                exit();
            }
        }
    }

    /*
     * somewhat redundant, but useful
     */
    public function isLocked(): bool
    {
        if ( file_exists( $this->lockfile ) )
        {
            if ( time() - filemtime( $this->lockfile ) > ( $this->config['lockfileLifetime'] ?? 120 ) )
            {
                unlink( $this->lockfile );
                $this->log( 'unlocked (lockfile too old)' );
                return false;
            }
            else
            {
                return true;
            }
        }

        return false;
    }

    /*
     * tell them what happened
     */
    public function respond( $code = 200, $message = '' )
    {
        http_response_code( $code );
        header( 'Content-Type: application/json; charset=utf-8' );
        echo json_encode(
            [
                'status' => $code,
                'message' => $message
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /*
     * move current data set around
     */
    public function revolveStorage( $dir, $operation = null ): void
    {
        switch ( $operation )
        {
            case 'restore':
                if ( is_dir( $this->tempDir ) )
                {
                    $this->delTree( $dir );
                    $this->moveDirectory( $this->tempDir, $dir );
                    rmdir( $this->tempDir );
                    $this->log( 'revolveStorage: restored flex objects' );
                }
                else {
                    $this->log( 'revolveStorage: no restorable content found' );
                }
                break;
            case 'delete':
                if ( is_dir( $this->tempDir ) )
                {
                    $this->delTree( $this->tempDir );
                    rmdir( $this->tempDir );
                    $this->log( 'revolveStorage: removed keeped flex objects' );
                }
                break;
            default:
                if ( is_dir( $dir ) )
                {
                    if ( is_dir( $this->tempDir ) )
                    {
                        $this->delTree( $this->tempDir );
                    }
                    $this->moveDirectory( $dir, $this->tempDir );
                    $this->log( 'revolveStorage: moving current flex objects to temp' );
                }
                else
                {
                    mkdir( $dir );
                    $this->log( 'revolveStorage: created fresh flex objects folder' );
                }
        }
    }

    /*
     * helper for recurcsive folder deletition
     */
    private function delTree( $dir ){
        if ( is_dir( $dir ) )
        {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ( $files as $fileinfo )
            {
                $todo = ( $fileinfo->isDir() ? 'rmdir' : 'unlink' );
                $todo( $fileinfo->getRealPath() );
            }
        }
        else
        {
            mkdir( $dir );
        }
    }

    /*
     * helper for recurcsive folder movement (rename() sucks a bit)
     */
    public function moveDirectory( $from, $to )
    {
        if ( ! is_dir( $from ) )
        {
            $this->log( 'moveDirectory: source directory does not exist' );
            return;
        }

        if ( ! is_dir( $to ) )
        {
            mkdir( $to, 0777, true );
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $files as $fileinfo )
        {
            $target = $to . DIRECTORY_SEPARATOR . $fileinfo->getBasename();

            if ( $fileinfo->isDir() )
            {
                mkdir( $target );
            }
            else
            {
                copy( $fileinfo->getPathname(), $target) ;
            }
        }

        $this->delTree( $from );
        $this->log( 'moveDirectory: directory moved successfully' );
    }

    /*
     * Handle content override manipulation
     * We can setup enviroment based overrides for certain fields
     */
    public function handleOverrides( $payload )
    {
        if (
            array_key_exists( 'env', $this->config )
            && array_key_exists( $this->config['env'], $this->config['envOverrides'] )
        )
        {
            foreach ( $this->config['envOverrides'][ $this->config['env'] ] as $field => $override )
            {
                if ( array_key_exists( $field, $payload ) && $payload[ $field ] == $override[0] )
                {
                    $payload[ $field ] = $override[1];
                }
            }
        }
        return $payload;
    }

    public function clearAssets()
    {
        $contentFolder = $this->config['assets'];
        if ( is_dir( $contentFolder ) )
        {
            $this->delTree( $contentFolder );
        }
    }


    /*
     * helper for recurcsive folder deletition
     */
    public function keysInArray( $array, $keys ) {
        foreach ( $keys as $key )
        {
            if ( !array_key_exists( $key, $array ) )
            {
                return false; // failure, if any key doesn't exist
            }
        }
        return true; // else true; it hasn't failed yet
    }

}
