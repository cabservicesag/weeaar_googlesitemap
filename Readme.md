Inofficial Fork of weeaar_googlesitemap
=======================================

based on official release 0.4.3 from TER:
http://typo3.org/extensions/repository/view/weeaar_googlesitemap

New Features
------------

  * tt_news single page views can now be optionally kept on sitemap.xml output (e.g. for dual-mode pages which contain both a LIST and SINGLE view on the same page and toggle the active tt_news mode based upon parameters passed on URL). Can be used by setting new parameter tt_news.single_page.*.keepListedAsPage = 1. See changeset cbe4a3fe159b09912fe777ec7ad618ce8a9be918 for details.

Fixes
-----

  * fix for security bulletin [TYPO3-EXT-SA-2014-010](http://typo3.org/teams/security/security-bulletins/typo3-extensions/typo3-ext-sa-2014-010/); credits to initiator of fork [cab services ag](https://github.com/cabservicesag/weeaar_googlesitemap)
  * runs under TYPO3 6.2
