<?php

namespace Osiset\ShopifyApp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Traits\ConfigAccessible;

use function Osiset\ShopifyApp\createHmac;

/**
 * Response for ensuring a proper webhook request.
 */
class AuthWebhook
{
    use ConfigAccessible;

    /**
     * Handle an incoming request to ensure webhook is valid.
     *
     * @param Request  $request The request object.
     * @param \Closure $next    The next action.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $hmac = $request->header('x-shopify-hmac-sha256') ?: '';
        $shopDomain = $request->header('x-shopify-shop-domain');
        $apiSecret= $this->getConfig('api_secret');

        if ($shopDomain) {
            $shop = resolve(IShopQuery::class)->getByDomain(ShopDomain::fromNative($shopDomain));

            if ($shop) {
                $apiSecret = $shop->apiHelper()->getApi()->getOptions()->getApiSecret();
            }
        }

        $data = $request->getContent();
        $hmacLocal = createHmac(['data' => $data, 'raw' => true, 'encode' => true], $apiSecret);

        if (!hash_equals($hmac, $hmacLocal) || empty($shopDomain)) {
            // Issue with HMAC or missing shop header
            return Response::make('Invalid webhook signature.', 401);
        }

        // All good, process webhook
        return $next($request);
    }
}
