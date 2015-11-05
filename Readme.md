Inofficial Fork of weeaar_googlesitemap
=======================================

based on official release 0.4.3 from TER:
http://typo3.org/extensions/repository/view/weeaar_googlesitemap

New Features
------------

  * tt_news single page views can now be optionally kept on `sitemap.xml` output (e.g. for dual-mode pages which contain both a `LIST` and `SINGLE` view on the same page and toggle the active tt_news mode based upon parameters passed on URL). Can be used by setting new parameter `tt_news.single_page.*.keepListedAsPage = 1`. See changeset `cbe4a3fe159b09912fe777ec7ad618ce8a9be918` for details.
  * tt_news entries can optionally be filtered by archived/non-archived state. If filtering is desired, set `archive_mode = archived` or `archive_mode = non-archived` on a single view configuration
  * tt_news records can be searched recursively from the given `pid_list` by setting `recursive = <recursion depth>` on the `single_page` configuration
  * news schema is no longer active by default as it is intended for actual news like magazine articles or press releases which if not the case for every tt_news article
    * enabled via `news_schema = 1` on `single_page` configuration
    * configuration allows setting all tags as described by [Google](https://support.google.com/news/publisher/answer/74288):
      * `news_schema.max_age_days` (optional) - Google requests to list only news which have an age of less than 2 days; you may override that limit using this setting
        * Note: News will still be listed as regular pages after if the maximum age has been exceeded, they just won't get the additional news schema.
      * `news_schema.publication.name` (required) - the publication's name (magazine name, news publisher name, organization, *not* the author)
      * `news_schema.publication.language` (required) - the language used by the publication as default
      * `news_schema.publication.language.<sys_language_uid> = <language name>` (optional) - mapping individual `sys_language_uid`s to language names (BE record UID, not FE `L`-parameter!)
      * `news_schema.keywords_default` (optional) - keywords to use if news article doesn't set any (may be overriden by saving spaces to news); keywords should be [Google news topics](https://support.google.com/news/publisher/answer/116037)
      * `news_schema.keywords_override` (optional) - keywords to use regardless of default or news values; keywords should be [Google news topics](https://support.google.com/news/publisher/answer/116037)
      * `news_schema.access` (optional) - subscription/registration required? assumed free if missing; see [Google help](https://support.google.com/news/publisher/answer/74288) for details
      * `news_schema.genres` (optional) - type of content; see [Google help](https://support.google.com/news/publisher/answer/93992) for details
      * `news_schema.stock_tickers` (optional) - related stock exchanges & ticker IDs; see [Google help](https://support.google.com/news/publisher/answer/74288) for details
    * example:
```
single_page.1 {
    news_schema = 1
    news_schema {
        publication {
            name = Example Magazine
            language = en
            language {
                0 = en
                1 = de
            }
        }
        access = Registration
        genres = UserGenerated
        keywords_default = Health
        #keywords_override = Health
        #stock_tickers = TEST:A
        max_age_days = 5
    }
}
```

Fixes
-----

  * fix for security bulletin [TYPO3-EXT-SA-2014-010](http://typo3.org/teams/security/security-bulletins/typo3-extensions/typo3-ext-sa-2014-010/); credits to initiator of fork [cab services ag](https://github.com/cabservicesag/weeaar_googlesitemap)
  * processing of translated tt_news entries
  * news schema now contains all required tags; see new features above
  * runs under TYPO3 6.2
