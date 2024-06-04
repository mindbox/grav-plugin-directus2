<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Cache;
use Grav\Common\Plugin;
use Grav\Events\FlexRegisterEvent;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexObject;
use Grav\Plugin\Directus2\Utils;
use Grav\Plugin\Directus2\DirectusUtility;

/**
 * Class Directus2Plugin
 * @package Grav\Plugin
 */
class Directus2Plugin extends Plugin
{
    public $features = [
        'blueprints' => 0,
    ];

    protected $utils;
    protected $directusUtil;
    protected $blueprints;

    /**
     * @var String
     */
    protected $lockfile = 'user/data/.directus2lock';

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0]
            ],
            FlexRegisterEvent::class => [['onRegisterFlex', 0]],
            'onTwigTemplatePaths'   => ['onTwigTemplatePaths', 1],
        ];
    }

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
            'onTwigInitialized' => ['onTwigInitialized', 0],
        ]);
    }

    /**
     * Register all flex blueprints automatically
     */
    public function onRegisterFlex($event): void
    {
        $flex = $event->flex;

        $path = $this->config["plugins.directus2"]['blueprints'];
        $this->blueprints = glob( $path . '/*.yaml' );

        if ( $this->blueprints )
        {
            foreach ( $this->blueprints as $blueprint )
            {
                $flex->addDirectoryType(
                    basename( $blueprint , '.yaml'),
                    $blueprint
                );
            }
        }
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths() : void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onPageInitialized()
    {
        $grav = $this->grav;
        $this->directusUtil = new DirectusUtility(
            $this->config(),
            $grav,
        );
        $this->utils = new Utils( $grav, $this->config() );


        // override the page with a maintenance tempalte while a process is running
        if ( $this->utils->isLocked() )
        {
            $page = $this->grav['page'];

            // $page->title( 'PLUGIN_DIRECTUS2.MAINTENANCE' );
            $page->template( 'd2maintenance' );
            $page->modifyHeader( 'http_response_code', 503 );
        }

        if ( strstr( $grav['uri']->route(), $this->config()['endpointName'] ) )
        {
            $this->processWebHooks($grav['uri']->route());
        }
    }

    public function onTwigInitialized()
    {
        $this->grav['twig']->twig()->addFunction(
            new \Twig_SimpleFunction('directusFile', [$this, 'returnDirectusFile'])
        );
        $this->grav['twig']->twig()->addFunction(
            new \Twig_SimpleFunction('directusTranslate', [$this, 'localizeObject'])
        );
    }

    public function localizeObject( mixed $object, string $lang )
    {
        if ( is_array( $object ) )
        {
            return $this->directusUtil->translate( $object, $lang );
        }
        return $object;
    }

    public function returnDirectusFile( mixed $fileReference, ?array $options = [] )
    {
        return $this->directusUtil->returnDirectusFile( $fileReference, $options );
    }

    private function requestItem( $collection, $id = 0, $depth = 2, $filters = [] )
    {
        $requestUrl = $this->directusUtil->generateRequestUrl( $collection, $id, $depth, $filters );
        return $this->directusUtil->get( $requestUrl );
    }

    private function processWebHooks( string $route )
    {
        $grav = $this->grav;
        $endpoint = $this->config()['endpointName'];

        // $grav['debugger']->addMessage( $grav['flex']->getCollection('currency') );
        // $grav['debugger']->addMessage( $grav['flex']->getDirectory('currency')->getConfig() );

        // dd( $this->grav['flex']->getCollection('currency')->getConfig() );

        switch ( $route )
        {
            case '/' . $endpoint . '/sync':
                $this->processSync();
                break;
            case '/' . $endpoint . '/create':
                $this->processCreate();
                break;
            case '/' . $endpoint . '/update':
                $this->processUpdate();
                break;
            case '/' . $endpoint . '/delete':
                $this->processDelete();
                break;

            case '/' . $endpoint . '/restore':
                $this->processRestore();
                break;

            case '/' . $endpoint . '/assets-reset':
                $this->processAssetReset();
                break;
        }
        return true;
    }

    private function processSync()
    {
        $grav = $this->grav;
        $this->utils->log( 'processSync: start' );
        $this->utils->checkLock();

        // is the server reachable?
        $pingStatusCode = $this->directusUtil->get('/server/ping')->getStatusCode();

        if( $pingStatusCode === 200 )
        {
            // let's go but nothing shall disturb us
            $this->utils->setLock();

            // what directories do we have to take care of?
            $blueprints = array_map( function( $path )
            {
                return pathinfo( $path, PATHINFO_FILENAME );
            }, $this->blueprints );

            try
            {
                foreach ( $blueprints as $collectionName )
                {
                    $this->utils->log( 'processing: ' . $collectionName );
                    $directory = $grav['flex']->getDirectory( $collectionName );
                    $config = $directory->getConfig()['directus'];

                    // get the collection
                    $response = $this->requestItem(
                        $collectionName,
                        0,
                        ( $config['depth'] ?? 2 ),
                        ( $config['filter'] ?? [] )
                    );

                    // process the collection
                    foreach ( $response->toArray()['data'] as $item )
                    {
                        // updat eor create the entry
                        $this->injectEntry( $item, $collectionName );
                    }
                }
            }
            catch( \Exception $e )
            {
                // something bad happened… bring it back to last state
                $this->utils->backupStorage( $this->config()['storage'], 'restore' );
                $this->utils->unLock();

                $this->utils->log( 'Exception. Trace: ' . $e->getMessage() );
                $this->utils->log( 'Exception. File: ' . $e->getFile() . ', ' . $e->getLine() );
                $this->utils->respond( 500, 'syncing collections failed' );
                exit();
            }

            // backup to restore in case of failure later
            $this->utils->backupStorage( $this->config()['storage'] );
            // success
            $this->utils->respond( 200, 'sync successful' );
            Cache::clearCache();
        }
        else
        {
            $this->utils->log ('processSync: ping to /server/ping not successful - data has not been updated', $pingStatusCode );
            $this->utils->respond( 504, 'ping to /server/ping not successful - data has not been updated' );
        }

        $this->utils->unlock();
        $this->utils->log( 'processSync: end' );
        exit();
    }

    private function processCreate()
    {
        $grav = $this->grav;

        $this->utils->log( 'processCreate: start' );
        // $this->utils->checkLock();
        $this->utils->setLock();

        $body = $grav['request']->getParsedBody();

        // did they call the correct action and do we have all information?
        if (
            $body['event'] == 'items.create'
            && $this->utils->keysInArray( $body, [ 'payload', 'key', 'collection' ] )
        )
        {
            try
            {
                $this->utils->log( 'processing in: ' . $body['collection'] );
                $directory = $grav['flex']->getDirectory( $body['collection'] );

                // request the whole entry from source, because we might need more recursion
                $config = $directory->getConfig()['directus'];

                // get the entry
                $response = $this->requestItem(
                    $body['collection'],
                    $body['key'],
                    ( $config['depth'] ?? 2 ),
                    ( $config['filter'] ?? [] )
                );

                // update or create it
                $this->injectEntry( $response->toArray()['data'], $body['collection'] );

                // success
                $this->utils->respond( 200, 'create successful' );
                Cache::clearCache();
            }
            catch( \Exception $e )
            {
                $this->utils->log( 'Exception. Trace: ' . $e->getMessage() );
                $this->utils->respond( 500, 'creating item failed' );
                $this->utils->unLock();
                exit();
            }
        }
        else
        {
            // No data or incorrect action
            $this->utils->log( 'incorrect action or no usable data provided' );
            $this->utils->respond( 406, 'incorrect action or no usable data provided' );
        }

        $this->utils->unLock();
        $this->utils->log( 'processCreate: end' );
        exit();
    }

    private function processUpdate()
    {
        $grav = $this->grav;

        $this->utils->log( 'processUpdate: start' );
        // $this->utils->checkLock();
        $this->utils->setLock();

        $body = $grav['request']->getParsedBody();

        // did they call the correct action and do we have all information?
        if (
            $body['event'] == 'items.update'
            && $this->utils->keysInArray( $body, [ 'payload', 'keys', 'collection' ] )
        )
        {
            try
            {
                $this->utils->log( 'processing in: ' . $body['collection'] );
                $directory = $grav['flex']->getDirectory( $body['collection'] );
                $collection = $directory->getCollection();
                $config = $directory->getConfig()['directus'];

                foreach ( $body['keys'] as $id )
                {
                    $object = $collection->get( $id );
                    if ( $object )
                    {
                        // request the whole entry from source, because we might need more recursion
                        $response = $this->requestItem(
                            $body['collection'],
                            $id,
                            ( $config['depth'] ?? 2 ),
                            ( $config['filter'] ?? [] )
                        );

                        // update or create it
                        $this->injectEntry( $response->toArray()['data'], $body['collection'] );
                    }
                    else
                    {
                        $this->utils->log( $id . ' does not exist in flex objects' );
                    }
                }

                // success
                $this->utils->respond( 200, 'update successful' );
                Cache::clearCache();
            }
            catch( \Exception $e )
            {
                $this->utils->log( 'Exception. Trace: ' . $e->getMessage() );
                $this->utils->respond( 500, 'updating collection failed' );
                $this->utils->unLock();
                exit();
            }
        }
        else
        {
            // No data or incorrect action
            $this->utils->log( 'incorrect action or no usable data provided' );
            $this->utils->respond( 406, 'incorrect action or no usable data provided' );
        }

        $this->utils->unLock();
        $this->utils->log( 'processUpdate: end' );
        exit();
    }

    private function processDelete()
    {
        $grav = $this->grav;

        $this->utils->log( 'processDelete: start' );
        $this->utils->checkLock();
        $this->utils->setLock();

        $body = $grav['request']->getParsedBody();

        // did they call the correct action and do we have all information?
        if (
            $body['event'] == 'items.delete'
            && $this->utils->keysInArray( $body, [ 'keys', 'collection' ] )
        )
        {
            try
            {
                $this->utils->log( 'processing in: ' . $body['collection'] );
                $directory = $grav['flex']->getDirectory( $body['collection'] );
                $collection = $directory->getCollection();

                foreach ( $body['keys'] as $id )
                {
                    $object = $collection->get( $id );
                    if ( $object )
                    {
                        $object->delete();
                        $this->utils->Log( 'deleted: ' . $id );
                    }
                    else
                    {
                        $this->utils->log( $id . ' does not exist in flex objects' );
                    }
                }

                // success
                $this->utils->respond( 200, 'update successful' );
                Cache::clearCache();
            }
            catch( \Exception $e )
            {
                $this->utils->log( 'Exception. Trace: ' . $e->getMessage() );
                $this->utils->respond( 500, 'deleting entries failed' );
                $this->utils->unLock();
            }
        }
        else
        {
            // No data or incorrect action
            $this->utils->log( 'incorrect action or no usable data provided' );
            $this->utils->respond( 406, 'incorrect action or no usable data provided' );
        }

        $this->utils->unLock();
        $this->utils->log( 'processDelete: end' );
        exit();
    }

    private function processRestore()
    {
        $this->utils->log( 'processRestore: start' );
        $this->utils->checkLock();
        $this->utils->setLock();

        // move current to temp, to restore in case of failure
        $this->utils->backupStorage( $this->config()['storage'], 'restore' );

        $this->utils->respond( 200, 'restoring complete' );

        $this->utils->unLock();
        $this->utils->log( 'processRestore: end' );
        exit();
    }

    private function processAssetReset()
    {
        $grav = $this->grav;
        $this->utils->log( 'processAssetReset: start' );
        $this->utils->checkLock();
        $this->utils->setLock();

        $this->utils->clearAssets();

        $this->utils->respond( 200, 'assets cleared' );

        $this->utils->unLock();
        $this->utils->log( 'processAssetReset: end' );
        exit();
    }

    private function injectEntry( $item, $collectionName )
    {
        $grav = $this->grav;

        $directory = $grav['flex']->getDirectory( $collectionName );
        $collection = $directory->getCollection();
        $object = $collection->get( $item['id'] );
        // special JMG langauge foo (to be undone )
        // $item = $this->refactorItem( $item );

        // in case it already/still exists
        if ( $object )
        {
            $object->update( $item );
            $object->save();
            $this->utils->Log( 'updated: ' . $item['id'] );
        }
        // or create new
        else
        {
            $objectInstance = new FlexObject( $item, $item['id'], $directory ) ;
            $object = $objectInstance->create( $item['id'] );
            $collection->add( $object );
            $this->utils->log( 'created: ' . $item['id'] );
        }
    }

    // JMG Special translation awareness || TO BE REFACTORED /phades out
    private function refactorItem(array $item) {
        if(key_exists('translations', $item)) {

            foreach ($item['translations'] as $masterKey => $translation) {
                $parsedCode = [];

                if(is_string($translation['languages_code'])) {
                    $parsedCode = explode('-', $translation['languages_code']);
                } else {
                    $parsedCode = explode('-', $translation['languages_code']['code']);
                }

                if(count($parsedCode) === 3 && $parsedCode[2] === $this->config()['env']['instance']) {
                    if(($parsedCode[0] . '-' . $parsedCode[1]) === $this->config()['env']['defaultLanguage']) {
                        foreach($translation as $key => $value) {
                            if($key !== 'languages_code' && $key !== 'id') {
                                $item[$key] = $value;
                            }
                        }
                    } else {
                        $langCodeToProcess = $parsedCode[0] . '-' . $parsedCode[1];
                        foreach($item['translations'] as $index => $trans2) {

                            if((is_string($trans2['languages_code']) ? $trans2['languages_code'] : $trans2['languages_code']['code']) === $langCodeToProcess) {
                                foreach($translation as $key => $value) {
                                    // do something
                                    if($key !== 'languages_code') {
                                        $item['translations'][$index][$key] = $value;
                                    }
                                }
                            }
                        }
                    }

                    unset($item['translations'][$masterKey]);
                } elseif ( count($parsedCode) === 3 ) {
                    unset($item['translations'][$masterKey]);
                }
            }
        }

        return $item;
    }
}
