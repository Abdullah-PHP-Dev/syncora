<?php

namespace App\Support\Http;

use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;

/**
 * Global Http client middleware: Graph API calls must carry
 * appsecret_proof (HMAC-SHA256 of the access token with the app secret)
 * when the Meta app has "Require App Secret" switched on - otherwise Meta
 * answers "API calls from the server require an appsecret_proof argument".
 *
 * Many services build Graph requests by hand and only some added the
 * proof. This adds it to any graph.facebook.com request that carries an
 * access token (query, form/JSON body or Bearer header) but no proof yet,
 * using the one Meta app's secret (posts.facebook, which ads.facebook
 * shares). Instagram Login (graph.instagram.com) has no such setting and
 * is left alone.
 */
class MetaAppSecretProof
{
    private const HOSTS = ['graph.facebook.com', 'graph-video.facebook.com'];

    public function __invoke(RequestInterface $request): RequestInterface
    {
        if (! in_array(strtolower($request->getUri()->getHost()), self::HOSTS, true)) {
            return $request;
        }

        $query = Query::parse($request->getUri()->getQuery());

        if (isset($query['appsecret_proof'])) {
            return $request;
        }

        $secret = (string) adminSetting('posts.facebook.client_secret');
        $token = $this->token($request, $query);

        if ($secret === '' || ! $token) {
            return $request;
        }

        return $request->withUri(Uri::withQueryValue($request->getUri(), 'appsecret_proof', hash_hmac('sha256', $token, $secret)));
    }

    private function token(RequestInterface $request, array $query): ?string
    {
        if (! empty($query['access_token']) && is_string($query['access_token'])) {
            return $query['access_token'];
        }

        if (preg_match('/^Bearer\s+(\S+)/i', $request->getHeaderLine('Authorization'), $m)) {
            return $m[1];
        }

        $type = strtolower($request->getHeaderLine('Content-Type'));
        $body = $request->getBody();

        // Multipart (file uploads) and streams can't be re-read safely.
        if (! $body->isSeekable() || $body->getSize() === null || $body->getSize() > 1048576) {
            return null;
        }

        $contents = (string) $body;
        $body->rewind();

        if (str_contains($type, 'application/x-www-form-urlencoded')) {
            $form = Query::parse($contents);

            return is_string($form['access_token'] ?? null) ? $form['access_token'] : null;
        }

        if (str_contains($type, 'application/json')) {
            $json = json_decode($contents, true);

            return is_string($json['access_token'] ?? null) ? $json['access_token'] : null;
        }

        return null;
    }
}
