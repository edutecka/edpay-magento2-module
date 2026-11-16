# Roadmap and Architecture Plan - EDPay Magento 2 Module

## Development Phases

### Phase 1: Module Skeleton (Complete)
- [x] Register the module in `registration.php` and `etc/module.xml`.

### Phase 2: Payment Configuration and Controllers (In Progress)
- [ ] Add payment configuration in `etc/frontend/di.xml` and `etc/adminhtml/system.xml`.
- [ ] Implement `AuthorizeCommand` and `CaptureCommand`.
- [ ] Add Magento routing for the IPN/webhook controller (`/edpay/webhook/index`).

### Phase 3: Marketplace and Packaging
- [ ] Publish to Packagist and the Adobe Commerce Marketplace.
