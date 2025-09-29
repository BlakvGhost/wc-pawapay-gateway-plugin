# PawaPay Gateway Translations

This folder contains the translation files for the WooCommerce PawaPay Gateway plugin.

## Available Files

- `wc-pawapay.pot` - Translation template (do not modify)
- `wc-pawapay-fr_FR.po` - French translation
- `wc-pawapay-fr_FR.mo` - French compiled file
- `wc-pawapay-en_US.po` - English translation
- `wc-pawapay-en_US.mo` - English compiled file

## Add a new language

1. Copy `wc-pawapay.pot` to `wc-pawapay-[locale].po`
2. Translate the strings with Poedit or a text editor
3. Compile the .po file to .mo with the command:

```bash
msgfmt wc-pawapay-[locale].po -o wc-pawapay-[locale].mo
```
