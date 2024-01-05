<?php
namespace Grav\Plugin\Directus2;


use Grav\Common\Grav;
use Symfony\Component\HttpClient\CurlHttpClient;

/**
 * Class DirectusUtility
 * @package Grav\Plugin\Directus\Utility
 */
class DirectusUtility
{
    /**
     * @var \Symfony\Component\HttpClient\CurlHttpClient
     */
    private $httpClient;

    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $apiServer;

    /**
     * @var string
     */
    private $token;

    /**
     * @var Grav
     */
    private $grav;

    /**
     * @var boolean
     */
    private $noCors = false;

    /**
     * DirectusUtility constructor.
     * @param array $config
     * @param Grav $grav
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function __construct( array $config, Grav $grav )
    {
        if ( $config )
        {
            $this->config = $config;
        }

        $this->httpClient = new CurlHttpClient();
        $this->grav = $grav;
        $this->apiServer = $config['directus']['directusAPIUrl'];

        if ( isset( $this->config['directus']['token'] ) )
        {
            $this->token = $this->config['directus']['token'];
        }
        else {
            $this->token = $this->requestToken( $config['directus']['email'], $config['directus']['password']);
        }

        if ( isset( $this->config['disableCors'] ) )
        {
            $this->noCors = $this->config['disableCors'];
        }
    }

    /**
     * @return mixed
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function requestToken ( string $email, string $password ) {
        $options = [
            'body' => [
                'email' => $email,
                'password' => $password
            ],
        ];

        $response = $this->httpClient->request(
            'POST',
            $this->apiServer . '/auth/authenticate',
            $options
        );

        return json_decode($response->getContent())->data->token;

    }

    /**
     * @param string $token
     */
    public function setToken(string $token)
    {
        $this->token = $token;
    }

    /**
     * @return string[]
     */
    private function getAuthorizationHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->token
        ];
    }

    /**
     * @param string $path
     * @return \Symfony\Contracts\HttpClient\ResponseInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function get($path = '')
    {
        $options = $this->getOptions();

        return $this->httpClient->request(
            'GET',
            $this->apiServer . $path,
            $options
        );
    }

    public function update(string $collection, int $id,  array $dataSet)
    {
        $options = $this->getOptions();

        $options['json'] = $dataSet;
        return $this->httpClient->request(
            'PATCH',
            $this->apiServer . '/items/' . $collection . '/' . $id,
            $options
        );
    }

    public function insert(string $collection,  array $dataSet)
    {
        $options = $this->getOptions();

        $options['json'] = $dataSet;
        return $this->httpClient->request(
            'POST',
            $this->apiServer . '/items/' . $collection,
            $options
        );
    }

    private function getOptions() {
        $options = [
            'headers' => $this->getAuthorizationHeaders()
        ];

        if ( $this->grav['debugger']->enabled() || $this->noCors )
        {
            $options['verify_peer'] = false;
            $options['verify_host'] = false;
        }

        return $options;
    }

    /**
     * @param string $collection
     * @param string $id
     * @param int $depth
     * @param array $filters
     * @param int $limit
     * @return string
     */
    public function generateRequestUrl(string $collection, string $id = '0', int $depth = 2, array $filters = [], int $limit = -1, string $sort = '') {
        $url = '/items/' . $collection . ($id ? '/' : null);

        if($id) {
            $url .= (string)$id;
        }
        $url .= '?';
        if($depth > 0) {
            $url .= 'fields=';
            for($i = 1; $i <= $depth; $i++) {
                $url .= '*';
                $i < $depth ? $url .= '.' : null;
            }
        }
        /*
        if ( isset( $filters['status'] ) )
        {
            if (
                isset( $this->grav['config']['system']['env']['state'] )
                && $this->grav['config']['system']['env']['state'] === 'preview'
            )
            {
                $filters['status']['operator'] = '_in';
                $filters['status']['value'] = $this->grav['plugins']['Grav\Plugin\DirectusPlugin']->config()['env']['status']['preview'];
            }
            else
            {
                $filters['status']['operator'] = '_eq';
                $filters['status']['value'] = $this->grav['plugins']['Grav\Plugin\DirectusPlugin']->config()['env']['status']['default'];
            }
        }
        */

        foreach($filters as $fields => $filter) {

            $fields = $this->parseFilterString($fields);

            $url .= '&filter' . $fields . (isset($filter['mm_field']) ? '[' . $filter['mm_field'] . ']' : '') . ( isset($filter['operator']) ? '[' . $filter['operator'] . ']' : null ) . '=' . $filter['value'];
        }
        $url .= '&limit=' . (string)$limit;
        if($sort) {
            $url .= '&sort=' . $sort;
        }

        return $url;
    }

    /**
     * @param string $filter
     * @return string
     */
    private function parseFilterString(string $filter) {

        $filterString = '';

        $filterArray = explode('.', $filter);

        foreach($filterArray as $field) {
            $filterString .= '[' . $field . ']';
        }

        return $filterString;
    }


    /**
     * @param array $object
     * @param string $lang
     * @return array
     */
    public function translate( array $object, string $lang )
    {
        foreach ( $object['translations'] as $translation )
        {
            if (
                is_array( $translation['languages_code'] )
                && ( $lang === substr( $translation['languages_code']['code'], 0, 2 ) )
            )
            {
                foreach ( $translation as $key => $value )
                {
                    if( $key !== 'id' && $value )
                    {
                        $object[$key] = $value;
                    }
                }
            }
            elseif (
                is_string( $translation['languages_code'] )
                && ( $lang === substr( $translation['languages_code'], 0, 2 ) )
            )
            {
                foreach ( $translation as $key => $value )
                {
                    if( $key !== 'id' && $value )
                    {
                        $object[$key] = $value;
                    }
                }
            }
        }
        return $object;
    }

    /**
     * @param array|null $fileReference
     * @param array|null $options
     * @return string
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function returnDirectusFile ( ?array $fileReference, ?array $options = [] )
    {
        // if ref is string
        // request file info first $url =  '/files/' . $fileReference['id'];
        // but since there will always be a request to the server this is not desirable

        if( is_array( $fileReference ) )
        {
            $contentFolder = $this->config['assets'];

            if ( ! is_dir( $contentFolder ) )
            {
                mkdir ( $contentFolder );
            }

            $path_parts = pathinfo( $fileReference['filename_download'] );
            if( ! isset( $path_parts['extension'] ) )
            {
                // panic!
                dd( $fileReference );
            }
            $hash = md5( json_encode( $options ) );
            $fileName = $path_parts['filename'] . '-' . $hash . '.' . $path_parts['extension'];

            $fullPath = $contentFolder . '/' . $fileName;

            $url =  '/assets/' . $fileReference['id'];
            $query = http_build_query( $options );
            $url = $query ? $url . '?' . $query : $url;

            if ( ! file_exists( $fullPath ) )
            {
                try
                {
                    $imageData = $this->get( $url )->getContent();
                    $fp = fopen( $fullPath,'x' );
                    fwrite( $fp, $imageData );
                    fclose( $fp );
                }
                catch ( \Exception $e )
                {
                    $this->grav['debugger']->addException($e);
                }
            }

            return '/' . $fullPath;

        } else {
            return null;
        }
    }
}
