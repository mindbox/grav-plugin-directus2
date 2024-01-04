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
     * @var string
     */
    private $apiServer;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $password;

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
    private $noCors;

    /**
     * DirectusUtility constructor.
     * @param string $apiUrl
     * @param Grav $grav
     * @param string $email
     * @param string $password
     * @param string $token
     * @param bool $noCors
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function __construct(string $apiUrl, Grav $grav, string $email = '', string $password = '', string $token = '', bool $noCors = false)
    {
        $this->httpClient = new CurlHttpClient();
        $this->apiServer = $apiUrl;
        $this->email = $email;
        $this->password = $password;
        $this->token = $token ? $token : $this->requestToken();
        $this->grav = $grav;
        $this->noCors = $noCors;
    }

    /**
     * @return mixed
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function requestToken () {
        $options = [
            'body' => [
                'email' => $this->email,
                'password' => $this->password
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
    public function setToken(string $token){
        $this->token = $token;
    }

    /**
     * @return string[]
     */
    private function getAuthorizationHeaders() {
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

    public function update(string $collection, int $id,  array $dataSet) {
        $options = $this->getOptions();

        $options['json'] = $dataSet;
        return $this->httpClient->request(
            'PATCH',
            $this->apiServer . '/items/' . $collection . '/' . $id,
            $options
        );
    }

    public function insert(string $collection,  array $dataSet) {
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
        if(isset($filters['status'])) {
            if(isset($this->grav['config']['system']['env']['state']) && $this->grav['config']['system']['env']['state'] === 'preview') {
                $filters['status']['operator'] = '_in';
                $filters['status']['value'] = $this->grav['plugins']['Grav\Plugin\DirectusPlugin']->config()['env']['status']['preview'];
            } else {
                $filters['status']['operator'] = '_eq';
                $filters['status']['value'] = $this->grav['plugins']['Grav\Plugin\DirectusPlugin']->config()['env']['status']['default'];
            }
        }

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
}
