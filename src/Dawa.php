<?php

/**
 * DAWA base functions.
 */
namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Dawa
{
    var $dawaBaseUri = 'https://dawa.aws.dk';

    /**
     * Construct the class.
     */
    public function __construct()
    {
    }

    /**
     * Request Dawa service endpoint.
     *
     * @param string $uri
     *   The endpoint uri.
     * @param array $query
     *   Additional named query parameters.
     *
     * @return bool|mixed
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($uri, $query = [], bool $debug = false)
    {
        try {
            $client = new Client([
                'base_uri' => $this->dawaBaseUri,
                'verify' => false
            ]);
            $response = $client->request('GET', $uri, [
                'query' => $query,
                'debug' => $debug,
            ]);

            if (!empty($response->getBody())) {
                return json_decode($response->getBody()->getContents());
            }
        } catch (RequestException $e) {
            // @todo: add exception handling.
        }

        return false;
    }

}
