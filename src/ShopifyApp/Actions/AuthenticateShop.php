<?php

namespace Osiset\ShopifyApp\Actions;

use Illuminate\Http\Request;
use Osiset\ShopifyApp\Contracts\ApiHelper as IApiHelper;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Services\ShopSession;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Actions\AfterAuthorize;
use Osiset\ShopifyApp\Actions\DispatchScripts;
use Osiset\ShopifyApp\Actions\DispatchWebhooks;

/**
 * Authenticates a shop and fires post authentication actions.
 */
class AuthenticateShop
{
    /**
     * The shop session handler.
     *
     * @var ShopSession
     */
    protected $shopSession;

    /**
     * The API helper.
     *
     * @var IApiHelper
     */
    protected $apiHelper;

    /**
     * The action for authorizing a shop.
     *
     * @var AuthorizeShop
     */
    protected $authorizeShopAction;

    /**
     * The action for dispatching scripts.
     *
     * @var DispatchScripts
     */
    protected $dispatchScriptsAction;

    /**
     * The action for dispatching webhooks.
     *
     * @var DispatchWebhooks
     */
    protected $dispatchWebhooksAction;

    /**
     * The action for after authorize actions.
     *
     * @var AfterAuthorize
     */
    protected $afterAuthorizeAction;

    /**
     * The shop query helper.
     *
     * @var IShopQuery
     */
    protected $shopQuery;

    /**
     * Setup.
     *
     * @param ShopSession      $shopSession            The shop session handler.
     * @param IApiHelper       $apiHelper              The API helper.
     * @param AuthorizeShop    $authorizeShopAction    The action for authorizing a shop.
     * @param DispatchScripts  $dispatchScriptsAction  The action for dispatching scripts.
     * @param DispatchWebhooks $dispatchWebhooksAction The action for dispatching webhooks.
     * @param AfterAuthorize   $afterAuthorizeAction   The action for after authorize actions.
     *
     * @return void
     */
    public function __construct(
        ShopSession $shopSession,
        IApiHelper $apiHelper,
        AuthorizeShop $authorizeShopAction,
        DispatchScripts $dispatchScriptsAction,
        DispatchWebhooks $dispatchWebhooksAction,
        AfterAuthorize $afterAuthorizeAction,
        IShopQuery $shopQuery
    ) {
        $this->shopSession = $shopSession;
        $this->shopQuery = $shopQuery;

        if ($this->shopSession->getShop()) {
            $this->apiHelper = $shopSession->getShop()->apiHelper();
        } else {
            $this->apiHelper = $apiHelper;
            $this->apiHelper->make();
        }

        $this->authorizeShopAction = $authorizeShopAction;
        $this->dispatchScriptsAction = $dispatchScriptsAction;
        $this->dispatchWebhooksAction = $dispatchWebhooksAction;
        $this->afterAuthorizeAction = $afterAuthorizeAction;
    }

    /**
     * Execution.
     *
     * @param Request $request The request object.
     *
     * @return array
     */
    public function __invoke(Request $request): array
    {
        // Setup
        $shopDomain = ShopDomain::fromNative($request->get('shop'));

        if (! $shopDomain->isNull() && ! $this->shopSession->getShop()) {
            $shop = $this->shopQuery->getByDomain($shopDomain);

            if ($shop) {
                // Generate api helper from shop
                $this->apiHelper = $shop->apiHelper();
            }
        }

        $code = $request->get('code');

        // Run the check
        $result = call_user_func($this->authorizeShopAction, $shopDomain, $code);
        if (! $result->completed) {
            // No code, redirect to auth URL
            return [$result, false];
        }

        // Determine if the HMAC is correct
        if (!$this->apiHelper->verifyRequest($request->all())) {
            // Throw exception, something is wrong
            return [$result, null];
        }

        // Fire the post processing jobs
        $shopId = $this->shopSession->getShop()->getId();
        call_user_func($this->dispatchScriptsAction, $shopId, false);
        call_user_func($this->dispatchWebhooksAction, $shopId, false);
        call_user_func($this->afterAuthorizeAction, $shopId);

        return [$result, true];
    }
}
