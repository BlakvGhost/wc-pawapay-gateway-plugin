# WooCommerce PawaPay Gateway Plugin

A WordPress payment gateway plugin that integrates **Mobile Money payments** via the **PawaPay API** with automatic currency conversion.  
It supports multi-country, multi-operator payments and ensures secure transaction validation using webhooks and return URLs.

---

## Liens Utiles

- **GitHub Repository**: [wc-pawapay-gateway-plugin](https://github.com/BlakvGhost/wc-pawapay-gateway-plugin)
- **Contact / Support**: [Kabirou ALASSANE](https://kabiroualassane.link)  
- **X**: [@BlakvGhost](https://x.com/BlakvGhost)  

---

## Features

- ✅ Full integration with WooCommerce
- ✅ Mobile Money payments via **PawaPay Payment Page**
- ✅ Automatic currency conversion (any supported currency → XOF / XAF depending on country)
- ✅ Supports both **free** and **API key-based** exchange rate providers
- ✅ Country and operator selection at checkout
- ✅ Mobile operator logos at checkout
- ✅ Multi-country support (West & Central Africa)
- ✅ Sandbox and Production modes
- ✅ Webhook support for secure payment status validation

---

## Supported Countries

### 🇧🇯 West Africa

- **🇧🇯 Bénin** → XOF  
- **🇧🇫 Burkina Faso** → XOF  
- **🇨🇮 Côte d’Ivoire** → XOF  
- **🇬🇭 Ghana** → GHS  
- **🇲🇱 Mali** → XOF  
- **🇳🇬 Nigéria** → NGN  
- **🇸🇳 Sénégal** → XOF  
- **🇸🇱 Sierra Leone** → SLE  
- **🇹🇬 Togo** → XOF  

### 🇨🇲 Central Africa

- **🇨🇲 Cameroun** → XAF  
- **🇨🇩 République Démocratique du Congo** → CDF  
- **🇨🇬 République du Congo** → XAF  
- **🇬🇦 Gabon** → XAF  

### 🇰🇪 East Africa

- **🇪🇹 Éthiopie** → ETB  
- **🇰🇪 Kenya** → KES  
- **🇲🇿 Mozambique** → MZN  
- **🇷🇼 Rwanda** → RWF  
- **🇹🇿 Tanzanie** → TZS  
- **🇺🇬 Ouganda** → UGX  

### 🇿🇲 Southern Africa

- **🇲🇼 Malawi** → MWK  
- **🇿🇲 Zambie** → ZMW  

---

## Currency Conversion

The plugin automatically converts from **any store currency** into the supported settlement currencies for PawaPay.

**How it works**

1. Configure an **API key** for [ExchangeRate API](https://www.exchangerate-api.com/) → plugin uses the **paid endpoint**.  
2. If no key → fallback to **free endpoint**.  
3. Conversion rates are cached for **6 hours**.

---

## Installation

1. Download the plugin ZIP
2. Go to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select the ZIP file
4. Activate the plugin
5. Go to **WooCommerce → Settings → Payments**
6. Enable and configure **PawaPay**

---

## Configuration

### Required Settings

- **API Token** – Your PawaPay API token  
- **Environment** – Sandbox or Production  
- **Merchant Name** – Shown on customer’s statement (max 22 chars)  
- **ExchangeRate API Key (optional)** – For reliable currency conversion

---

## Return URL & Webhooks

- PawaPay redirects customers on success/failure  
- Webhooks update WooCommerce order status automatically  

---

## Hooks & Filters

**Actions**  

- `pawapay_before_payment_processing` – Before payment  
- `pawapay_after_payment_processing` – After payment  
- `pawapay_payment_success` – On success  
- `pawapay_payment_failed` – On failure  

**Filters**  

- `pawapay_supported_countries` – Modify supported countries  
- `pawapay_supported_currencies` – Modify supported currencies  
- `pawapay_provider_list` – Modify mobile operators  
- `pawapay_payment_description` – Customize description  

---

## Troubleshooting

**Gateway not showing**: Check WooCommerce active, store currency supported, API token configured  
**Currency errors**: Free endpoint may fail → add ExchangeRate API key  
**WooCommerce Blocks issues**: Update WooCommerce & clear cache  

---

## Changelog

### Version 1.3.0

- Added all PawaPay settlement currencies  
- ExchangeRate API integration  
- Improved currency conversion & webhook support  

### Version 1.2.2

- WooCommerce Blocks fixes  
- Performance & error handling improvements  

### Version 1.2.1

- Multi-country & operator selection  
- Automatic currency conversion  

### Version 1.1.0

- Initial release

---

## Roadmap

- [ ] Advanced transaction dashboard  
- [ ] Reports & analytics  
- [ ] More African countries  
- [ ] Loyalty system

---

## Support

- [PawaPay Docs](https://docs.pawapay.io/v2/docs)  
- Open a [GitHub issue](https://github.com/BlakvGhost/wc-pawapay-gateway-plugin/issues)  
- Contact [Kabirou ALASSANE](https://kabiroualassane.link)

---

## License

GPLv3

---

## Contributors

- [Kabirou ALASSANE](https://kabiroualassane.link)  

---

**Note**: Requires valid PawaPay API key. Sign up at [PawaPay](https://pawapay.io).
