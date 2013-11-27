=== Localize WordPress ===
Contributors: sushkov
Tags: locale, switch, localization, translations, glotpress
Requires at least: WordPress 2.9
Tested up to: WordPress 3.2
Stable tag: 0.5
Donate link: http://stas.nerd.ro/pub/donate/

Easily switch to any localization from GlotPress


== Description ==

This plugin allows you to switch your WordPress installation to use any of the
languages available on [GlotPress](http://translate.wordpress.org)

Some of the features:

* No gettext compiler required!
* Does all the dirty work from editing `wp-config.php` to downloading the right files
* Can switch between versions. Available: stable and dev
* Uses GlotPress api!

[vimeo http://vimeo.com/19433386]


== Installation ==

Please follow the [standard installation procedure for WordPress plugins](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).


== Frequently Asked Questions ==

Please report bugs on [plugin page issues tracker](https://github.com/stas/localize/issues).


== Changelog ==

= 0.5 =
* Added translations for:
  * Spanish, thanks to [Jhon Navarro](http://www.nrkmusik.es)

= 0.4 =
* Added support for multiple versions, all available from GlotPress
* Added some caching for API data (see `LOCALIZE_CACHE`)

= 0.3 =
* Fixed an incomplete translation string
* Added pot file
* Added translations for:
  * Romanian
  * Portuguese, thanks to [Techload Informatica](http://www.techload.com.br)

= 0.2 =
* Integrated with GlotPress api
* Thanks to [Nikolay Bachiyski](http://profiles.wordpress.org/users/nbachiyski) plugin uses `.mo` files

= 0.1 =
* The pilot release.
