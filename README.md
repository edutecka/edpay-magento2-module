# Módulo de Pagamento EDPay para Magento 2 / Adobe Commerce

[![Licença: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Versão Magento](https://img.shields.io/badge/Magento-2.4.x-orange.svg)](https://magento.com)
[![Multi-Gateway](https://img.shields.io/badge/EDPay-Multi--Gateway-0051FA.svg)](https://edpay.co.mz)

Módulo oficial para integrar a plataforma **EDPay** em lojas virtuais **Magento 2 / Adobe Commerce**, disponibilizando pagamentos em Meticais (MZN) através de **M-Pesa**, **e-Mola**, **Cartões Visa / Mastercard** e **Transferência Bancária**.

---

## 📋 Requisitos

- Magento Open Source / Adobe Commerce 2.4.x
- PHP 8.1 ou 8.2
- Extensão PHP `curl` e `json`
- Conta de Comerciante ativa na [EDPay](https://edpay.co.mz)

---

## 📦 Instalação via Composer

No diretório raiz da sua instalação Magento 2:

```bash
composer require edpay/magento2-module
bin/magento module:enable Edpay_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:clean
```

---

## ⚙️ Configuração no Painel Magento

1. Acesse o **Admin do Magento > Stores > Configuration > Sales > Payment Methods**.
2. Localize a seção **EDPay Multi-Gateway Payment**.
3. Configure os seguintes campos:
   - **Enabled**: `Yes`
   - **Title**: `EDPay (M-Pesa, e-Mola, Cartões, Banco)`
   - **Public Key**: Insira a chave pública (`pk_live_...` ou `pk_test_...`)
   - **Private Key**: Insira a chave privada correspondente (`sk_live_...`)
   - **Webhook Secret**: Insira o segredo `whsec_...`
   - **Sandbox Mode**: `No` para produção, `Yes` para testes
4. Salve a configuração e limpe o cache (`bin/magento cache:clean`).

---

## 🔗 Configuração do Webhook na EDPay

No painel EDPay (**Desenvolvedores > Webhooks**):
- **URL do Webhook**: `https://sua-loja.co.mz/edpay/webhook/index`
- **Eventos Monitorados**: `payment.succeeded`, `payment.failed`

Ao receber a confirmação `payment.succeeded`, o módulo emite a fatura (Invoice) no Magento e altera o estado da encomenda para `Processing`.

---

## 📄 Licença

MIT License. Consulte `LICENSE` para mais detalhes.
