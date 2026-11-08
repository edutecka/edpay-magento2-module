# Roadmap & Plano de Arquitetura - EDPay Magento 2 Module

## Fases de Desenvolvimento

### Fase 1: Arquitetura & Configuração XML (Concluído)
- [x] Estrutura de módulo `registration.php` e `etc/module.xml`.
- [x] Definição de pagamentos em `etc/frontend/di.xml` e `etc/adminhtml/system.xml`.

### Fase 2: Gateway Command Pool & Controllers (Em Desenvolvimento)
- [ ] Implementação de `AuthorizeCommand` e `CaptureCommand`.
- [ ] Controller de callback de IPN / Webhooks (`/edpay/webhook/index`).

### Fase 3: Marketplace & Empacotamento
- [ ] Publicação no Packagist e Adobe Commerce Marketplace.
