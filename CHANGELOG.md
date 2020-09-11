### 2020-09-11

Run `php artisan vendor:publish --tag=shopify-migrations && php artisan migrate`

- Add shopify_domain and shopify_token fields in shops table
- Use shopify_domain and shopify_token field to identify the shop instead of name and password fields


