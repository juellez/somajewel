# somajewel
somatic practice app

## app
The practice/tracking app which currently uses the WP auth/login and DB connector modules. This will be abstracted so the app can use its own DB and auth modules.

## wordpress
The WP admin used for monitoring usage stats for testing.

## License

SomaJewel is community software: anyone can use it, change it, and build on it, as long as improvements are shared back and the original work is credited.

Each part carries its own license file:

| Part | License |
|---|---|
| App (`app/`) | [AGPL-3.0-or-later](app/LICENSE) |
| Practice content (`app/content/`) | [CC BY-SA 4.0](app/content/LICENSE) |
| WordPress stats plugin (`wordpress/somajewel-stats/`) | [GPL-2.0-or-later](wordpress/somajewel-stats/LICENSE) |

The root [LICENSE](LICENSE) is the AGPL and covers anything else in the repository.

In plain terms:

- **Use it freely**, including for your own practice or your own clients.
- **Share your changes.** If you modify the app and run it for other people, you must offer them your modified source code under the same license. The app's Terms & Privacy page links to the source for this; set `source_url` in `config.php` to point at your version.
- **Keep the credit.** Keep the [NOTICE](NOTICE) file and the attribution: "SomaJewel, by Unstruck Somatics, LLC" for the code, and "Practice content by Jewel Mlnarik" for the content.
- **Content shares alike.** If you adapt the practices or prompts, share your version under CC BY-SA 4.0 with credit.

Code © 2026 Unstruck Somatics, LLC. Practice content © 2026 Jewel Mlnarik. See [NOTICE](NOTICE) for details.
