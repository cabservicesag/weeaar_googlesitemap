<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2006 Marcel Alburg <alb@weeaar.com>
 *
 *  Changes by Andreas Boehm <ab@berg.net> [June 2007]
 *    - Bugfixing tt_news: The param cat_id_list dosen't work
 *      --> Changing the sqlcmd to select the right tt_news items
 *    - tt_news: Add config param "backpid" to set the backlink from a
 *      single page
 *    - Optical improvement of the XML output (add some \n)
 *       
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

if (!class_exists('tslib_pibase')) {
	require_once(PATH_tslib . 'class.tslib_pibase.php');
}

/**
 * Plugin 'google_sitemap' for the 'weeaar_googlesitemap' extension.
 *
 * @author	Marcel Alburg <alb@weeaar.com>
 * @package	TYPO3
 * @subpackage	tx_weeaargooglesitemap
 */
class tx_weeaargooglesitemap_pi1 extends tslib_pibase {

	var $prefixId = 'tx_weeaargooglesitemap_pi1';  // Same as class name
	var $scriptRelPath = 'pi1/class.tx_weeaargooglesitemap_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'weeaar_googlesitemap'; // The extension key.
	var $pi_checkCHash = TRUE;
	var $tRows = array();
	var $url;
	var $usedSites = array();
	var $allowedDoktypes = array(2, 1);
	var $excludedPages = array();
	var $localizedIds = array();
	var $languageParamIf0 = TRUE;
	var $validCode = array('google', 'sitemap_org');
	var $useNewsSchema = false;

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {
		$this->conf = $conf;

		$this->db = $GLOBALS["TYPO3_DB"];

		$this->languageVar = (isset($this->conf["languageVar"]) ? $this->conf["languageVar"] : "L");

		$this->showLanguages = (isset($this->conf["showLanguages"])) ? explode(",", $this->conf["showLanguages"]) : array(0);

		$this->languageParamIf0 = (isset($this->conf['languageParamIf0'])) ? (int) $this->conf['languageParamIf0'] : TRUE;

		if (isset($this->conf['allowedDoktypes'])) {
			$this->allowedDoktypes = explode(",", $this->conf['allowedDoktypes']);
		}

		$this->getData();


		$this->sys_language_uid = $this->conf['sys_language_uid'] ? $this->conf['sys_language_uid'] : $GLOBALS['TSFE']->sys_language_uid;

		$pid_list = trim($this->cObj->stdWrap($this->conf['pid_list'], $this->conf['pid_list.']));
		$pid_list = $pid_list ? implode(t3lib_div::intExplode(',', $pid_list), ',') : $GLOBALS['TSFE']->id;

		$recursive = $this->cObj->stdWrap($this->conf['recursive'], $this->conf['recursive.']);
		$recursive = is_numeric($recursive) ? $recursive : 0;

		$this->pid_list = $this->pi_getPidList($pid_list, $recursive);
		$this->pid_list = $this->pid_list ? $this->pid_list : 0;

		$this->defaultCode = (isset($this->conf['defaultCode']) && in_array($this->conf['defaultCode'], $this->validCode)) ? $this->conf['defaultCode'] : 'google';

		$this->url = $this->give_domain();

		foreach (explode(",", $this->pid_list) as $pid) {
			$page = $GLOBALS["TSFE"]->sys_page->getPage($pid);
			if (in_array("0", $this->showLanguages)) {
				$this->generateItem($page);

				if ($page['uid'] != '' && in_array($page['doktype'], $this->allowedDoktypes)) {
					$this->localizedIds[] = $page['uid'];
				}
			} else {
				if ($page['uid'] != '' && in_array($page['doktype'], $this->allowedDoktypes)) {
					$this->localizedIds[] = $page['uid'];
				}
			}
		}

		$tree = $this->cObj->getTreeList($this->pid_list, 1000);

		$tRows = array();
		$treeIds = explode(',', $tree);

		foreach ($treeIds as $menuPid) {
			if ($menuPid) {
				$page = $GLOBALS["TSFE"]->sys_page->getPage($menuPid);

				if (in_array("0", $this->showLanguages)) {
					$this->generateItem($page);

					if ($page['uid'] != '' && in_array($page['doktype'], $this->allowedDoktypes)) {
						$this->localizedIds[] = $page['uid'];
					}

					$menuItems_level1 = $GLOBALS["TSFE"]->sys_page->getMenu($menuPid);

					reset($menuItems_level1);
					while (list ($uid, $pages_row) = each($menuItems_level1)) {
						$this->generateItem($pages_row);

						if ($pages_row['uid'] != '' && in_array($page['doktype'], $this->allowedDoktypes)) {
							$this->localizedIds[] = $pages_row['uid'];
						}
					}
				} else {
					if ($page['uid'] != '' && in_array($page['doktype'], $this->allowedDoktypes)) {
						$this->localizedIds[] = $page['uid'];
					}
				}
			}
		}

		if (count($this->localizedIds) > 0) {
			$res = $this->db->exec_SELECTquery("*", "pages_language_overlay", "pid in (" . implode(",", $this->localizedIds) . ") and sys_language_uid in (" . implode(",", $this->showLanguages) . ")");
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$this->generateItem($row, 'pid');
			}
		}

		/* add tt_news links */
		$this->makeNewsItems();


		$totalMenu = '<?xml version="1.0" encoding="UTF-8"?>';

		$totalMenu .= $this->getHeader();

		$totalMenu .= implode("\n", $this->tRows) . '</urlset>';

		return $totalMenu;
	}

	function makeNewsItems() {
		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_news'])) {
			require_once(t3lib_extMgm::extPath('tt_news') . 'pi/class.tx_ttnews.php');

			$ttnews = t3lib_div::makeInstance('tx_ttnews');

			if (is_array($this->conf["tt_news."]["single_page."]) && count($this->conf["tt_news."]["single_page."]) > 0) {
				foreach ($this->conf["tt_news."]["single_page."] as $no => $test) {
					if (!strpos($no, ".")) {
						if (isset($this->conf["tt_news."]["single_page."]["{$no}."]["pid_list"]) && $this->conf["tt_news."]["single_page."]["{$no}."]["pid_list"] != "") {
							$now_utime = time();
							$pid_list = preg_replace('/[^0-9,]/', '', $this->conf["tt_news."]["single_page."]["{$no}."]["pid_list"]); // simple sanitization

							// get parameter for storage page recursion
							$recursive = 0;
							if (isset($this->conf["tt_news."]["single_page."]["{$no}."]["recursive"])) {
								$recursive = $this->cObj->stdWrap($this->conf["tt_news."]["single_page."]["{$no}."]["recursive"], $this->conf["tt_news."]["single_page."]["{$no}."]["recursive."]);
								$recursive = is_numeric($recursive) ? $recursive : 0;
							}

							// expand storage pages if recursion has been requested
							if ($recursive > 0) {
								$original_pid_list = $pid_list;

								$pid_list = implode(t3lib_div::intExplode(',', $pid_list), ',');
								$pid_list = $this->pi_getPidList($pid_list, $recursive);

								// reset to original list if expansion failed
								// (otherwise the SQL query would fail)
								if ($pid_list == '') {
									$pid_list = $original_pid_list;
								}
							}

							// should news schema be used to annotate this news block for news crawlers?
							// only use if activated (news_schema = 1) and required tags have been entered
							// see: https://support.google.com/news/publisher/answer/74288?hl=en
							$block_uses_news_schema = isset($this->conf["tt_news."]["single_page."]["{$no}."]["news_schema"]) &&
													  ($this->conf["tt_news."]["single_page."]["{$no}."]["news_schema"] == 1) &&
													  isset($this->conf["tt_news."]["single_page."]["{$no}."]["news_schema."]["publication."]["name"]) &&
													  (trim($this->conf["tt_news."]["single_page."]["{$no}."]["news_schema."]["publication."]["name"]) != '') &&
													  isset($this->conf["tt_news."]["single_page."]["{$no}."]["news_schema."]["publication."]["language"]) &&
													  (trim($this->conf["tt_news."]["single_page."]["{$no}."]["news_schema."]["publication."]["language"]) != '');
							$this->useNewsSchema |= $block_uses_news_schema;

							// filter by archive state?
							$include_archived = true;
							$include_non_archived = true;
							if (isset($this->conf["tt_news."]["single_page."]["$no."]["archive_mode"])) {
								$archive_mode = trim(strtoupper($this->conf["tt_news."]["single_page."]["$no."]["archive_mode"]));
								if ($archive_mode == 'ARCHIVED') {
									$include_non_archived = false;
								} else if ($archive_mode == 'NON-ARCHIVED') {
									$include_archived = false;
								}
							}

							// Look in the Config: If a backpid is set, transfer this information to the $param array
							if (isset($this->conf["tt_news."]["single_page."]["$no."]["backpid"])) {
								$tt_news_backpid = $this->conf["tt_news."]["single_page."]["$no."]["backpid"];
							} else {
								$tt_news_backpid = "";
							}

							// get all categories
							if (isset($this->conf["tt_news."]["single_page."]["$no."]["cat_id_list"])) {
								// Ok, we must look, if the news is linked to the category
								$cat_id_lists = explode(",", $this->conf["tt_news."]["single_page."]["$no."]["cat_id_list"]);
								if (count($cat_id_lists)) {
									for ($i = 0; $i < count($cat_id_lists); $i++) {
										if ($i == 0)
											$sql_addon = " and (";
										else
											$sql_addon.=" or ";
										$sql_addon.="tt_news_cat_mm.uid_foreign=" . intval($cat_id_lists[$i]);
									}
									$sql_addon.=")";
								}

								// get all original language or untranslated news by categories
								$res = $this->db->exec_SELECTquery("tt_news.uid, tt_news.title, tt_news.datetime, tt_news.sys_language_uid, tt_news.tstamp, tt_news.keywords, tt_news_cat_mm.uid_foreign, tt_news.archivedate", "tt_news, tt_news_cat_mm", "tt_news.l18n_parent = 0 and tt_news_cat_mm.uid_local = tt_news.uid and tt_news.pid in (" . $pid_list . ") and tt_news.hidden != 1 and tt_news.deleted != 1 and (tt_news.starttime = 0 OR tt_news.starttime <= '$now_utime') and (tt_news.endtime = 0 OR tt_news.endtime >= '$now_utime')$sql_addon", "tt_news.uid");

								while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
									$row[backpid] = $tt_news_backpid;
									$this->data["news"][$this->conf["tt_news."]["single_page."][$no]][] = $row;
								}

								// get all translated news by translation parent's categories
								// has to use parent UID
								// has to also check parent visibility
								// archive state is only checked against parent
								$res = $this->db->exec_SELECTquery("tt_news_parent.uid, tt_news.title, tt_news.datetime, tt_news.sys_language_uid, tt_news.tstamp, tt_news.keywords, tt_news_cat_mm.uid_foreign, tt_news_parent.archivedate", "tt_news, tt_news_cat_mm, tt_news as tt_news_parent", "tt_news_parent.uid = tt_news.l18n_parent and tt_news.l18n_parent != 0 and tt_news_cat_mm.uid_local = tt_news_parent.uid and tt_news.pid in (" . $pid_list . ") and tt_news.hidden != 1 and tt_news.deleted != 1 and (tt_news.starttime = 0 OR tt_news.starttime <= '$now_utime') and (tt_news.endtime = 0 OR tt_news.endtime >= '$now_utime') and tt_news_parent.hidden != 1 and tt_news_parent.deleted != 1 and (tt_news_parent.starttime = 0 OR tt_news_parent.starttime <= '$now_utime') and (tt_news_parent.endtime = 0 OR tt_news_parent.endtime >= '$now_utime')$sql_addon", "tt_news.uid");

								while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
									$row[backpid] = $tt_news_backpid;
									$this->data["news"][$this->conf["tt_news."]["single_page."][$no]][] = $row;
								}
							}
							else {
								// get all original language or untranslated news
								$res = $this->db->exec_SELECTquery("uid, title, datetime, sys_language_uid, tstamp, keywords, archivedate", "tt_news", "tt_news.l18n_parent = 0 and pid in (" . $pid_list . ") and hidden != 1 and deleted != 1 and (starttime = 0 OR starttime <= '$now_utime') and (endtime = 0 OR endtime >= '$now_utime')");

								while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
									$row[backpid] = $tt_news_backpid;
									$this->data["news"][$this->conf["tt_news."]["single_page."][$no]][] = $row;
								}

								// get all translated news
								// has to use parent UID
								// has to also check parent visibility
								// archive state is only checked against parent
								$res = $this->db->exec_SELECTquery("tt_news_parent.uid, tt_news.title, tt_news.datetime, tt_news.sys_language_uid, tt_news.tstamp, tt_news.keywords, tt_news_parent.archivedate", "tt_news, tt_news as tt_news_parent", "tt_news.l18n_parent = tt_news_parent.uid and tt_news.l18n_parent != 0 and tt_news.pid in (" . $pid_list . ") and tt_news.hidden != 1 and tt_news.deleted != 1 and (tt_news.starttime = 0 OR tt_news.starttime <= '$now_utime') and (tt_news.endtime = 0 OR tt_news.endtime >= '$now_utime') and tt_news_parent.hidden != 1 and tt_news_parent.deleted != 1 and (tt_news_parent.starttime = 0 OR tt_news_parent.starttime <= '$now_utime') and (tt_news_parent.endtime = 0 OR tt_news_parent.endtime >= '$now_utime')");

								while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
									$row[backpid] = $tt_news_backpid;
									$this->data["news"][$this->conf["tt_news."]["single_page."][$no]][] = $row;
								}
							}

							// filter by archive state
							if (!$include_archived || !$include_non_archived) {
								$filtered_rows = array();

								if (is_array($rows = $this->data["news"][$this->conf["tt_news."]["single_page."][$no]])) {
									foreach ($rows as $row) {
										$is_archived = ($row['archivedate'] != 0) && ($now_utime > $row['archivedate']);
										if ($is_archived && $include_archived) {
											$filtered_rows[] = $row;
										} else if (!$is_archived && $include_non_archived) {
											$filtered_rows[] = $row;
										}
									}
								}

								$this->data["news"][$this->conf["tt_news."]["single_page."][$no]] = $filtered_rows;
							}

							// copy configured news schema meta data
							// (configuration may not be uniform if multiple single_page entries have been defined)
							if ($block_uses_news_schema) {
								$num_rows = count($this->data["news"][$this->conf["tt_news."]["single_page."][$no]]);
								for ($i=0; $i<$num_rows; $i++) {
									$this->data["news"][$this->conf["tt_news."]["single_page."][$no]][$i]['news_schema'] = $this->conf["tt_news."]["single_page."]["{$no}."]["news_schema."];
								}
							}
						}
					}
				}
				$counter1 = $counter2 = $counter3 = 0;
				if (count($this->data["news"])) {
					foreach ($this->data["news"] as $singlePid => $array) {
						foreach ($array as $row) {
							$counter3++;
							//$ttnews->getHrDateSingle($row['datetime']);

							if ($row[backpid]) {
								$tt_news_backpid = "&tx_ttnews[backPid]=" . $row[backpid];
							} else {
								$tt_news_backpid = "";
							}

							$param = array(
								"parameter" => $singlePid,
								"useCacheHash" => 1,
								"additionalParams" => "&tx_ttnews[tt_news]={$row['uid']}$tt_news_backpid",
							);

							$allowed = 0;

							if (isset($row["sys_language_uid"]) && in_array($row["sys_language_uid"], $this->showLanguages)) {
								if ($this->languageParamIf0 || ($row["sys_language_uid"] != 0)) {
									$param["additionalParams"] .= "&{$this->languageVar}={$row["sys_language_uid"]}";
								}
								$allowed = 1;
							} else {
								$allowed = 0;
							}

							if ($allowed == 1) {
								$disabledParameter = explode(",", $this->conf["tt_news."]["disabledParameter"]);

								if ($ttnews->piVars['day'] && !in_array("day", $disabledParameter)) {
									$param["additionalParams"] .= '&tx_ttnews[day]=' . intval($ttnews->piVars['day']);
								}
								if ($ttnews->piVars['month'] && !in_array("month", $disabledParameter)) {
									$param["additionalParams"] .= '&tx_ttnews[month]=' . intval($ttnews->piVars['month']);
								}
								if ($ttnews->piVars['year'] && !in_array("year", $disabledParameter)) {
									$param["additionalParams"] .= '&tx_ttnews[year]=' . intval($ttnews->piVars['year']);
								}

								$link = $this->url . ltrim($this->cObj->typoLink($next, $param), '/');
								$link = preg_replace("/<a href=\"(.*)\".*/", "\\1", $link);

								$string = "   <loc>{$link}</loc>\n";
								$string .= "   <lastmod>" . gmdate("Y-m-d\TH:i:s\Z", $row["tstamp"]) . "</lastmod>\n";
								$string .= "   <priority>0.5</priority>\n";

								/* news sitemap */
								if ($this->useNewsSchema && isset($row['news_schema'])) {
									// check news age
									// Google says we should stop listing news 2 days after publication
									$max_age_days = (isset($row['news_schema']['max_age_days']) ? intval($row['news_schema']['max_age_days']) : 2);
									$age_days = ($now_utime - $row['datetime']) / 86400.0;
									$is_older_than_allowed = ($age_days > $max_age_days);

									if (!$is_older_than_allowed) {
										// find language
										// start with default and override with sys_language_uid mapping
										$language = $row['news_schema']['publication.']['language'];
										if (isset($row['news_schema']['publication.']['language.'])) {
											if (isset($row['news_schema']['publication.']['language.'][''.$row['sys_language_uid']]) && (trim($row['news_schema']['publication.']['language.'][''.$row['sys_language_uid']]) != '')) {
												$language = trim($row['news_schema']['publication.']['language.'][''.$row['sys_language_uid']]);
											}
										}

										$string .= "   <news:news>\n";

										$string .= "      <news:publication>\n";
										$string .= "         <news:name>" . htmlspecialchars($row['news_schema']['publication.']['name']) . "</news:name>\n";
										$string .= "         <news:language>" . htmlspecialchars($language) . "</news:language>\n";
										$string .= "      </news:publication>\n";

										$string .= "      <news:publication_date>" . gmdate("Y-m-d\TH:i:s\Z", $row["datetime"]) . "</news:publication_date>\n";

										$string .= "      <news:title>" . htmlspecialchars($row["title"]) . "</news:title>\n";

										if (isset($row['news_schema']['access'])) {
											$string .= '      <news:access>' . htmlspecialchars($row['news_schema']['access']) . "</news:access>\n";
										}

										if (isset($row['news_schema']['genres'])) {
											$string .= '      <news:genres>' . htmlspecialchars($row['news_schema']['genres']) . "</news:genres>\n";
										}

										// find keywords to use
										// 1) start with default or empty string
										$keywords = isset($row['news_schema']['keywords_default']) ? $row['news_schema']['keywords_default'] : '';
										// 2) replace by news keywords if set
										if ($row['keywords'] != '') {
											$keywords = $row['keywords'];
										}
										// 3) allow override by config (including empty strings)
										if (isset($row['news_schema']['keywords_override'])) {
											$keywords = $row['news_schema']['keywords_override'];
										}
										$keywords = trim($keywords);
										if ($keywords != '') {
											$string .= "      <news:keywords>" . htmlspecialchars($keywords) . "</news:keywords>\n";
										}

										if (isset($row['news_schema']['stock_tickers'])) {
											$string .= '      <news:stock_tickers>' . htmlspecialchars($row['news_schema']['stock_tickers']) . "</news:stock_tickers>\n";
										}

										$string .= "   </news:news>\n";
									}
								}
								$this->tRows[] = "<url>\n{$string}</url>\n";
							}
						}
					}
				}
			}
		}
	}

	function check_cat_ids($ids, $id) {
		$ids = explode(",", $ids);

		if (count($ids) > 0) {
			foreach ($ids as $cat_id) {
				if (isset($this->data["news_cat"][$cat_id])) {
					if (in_array($id, $this->data["news_cat"][$cat_id])) {
						return true;
					}
				}
			}
		}

		return false;
	}

	function generateItem($pages_row, $id_name = 'uid') {
		if (!$this->data[$pages_row[$id_name]][0] == 1 && count($pages_row) > 0 && (!in_array($pages_row[$id_name], $this->usedSites) || isset($pages_row["sys_language_uid"])) && (in_array($pages_row['doktype'], $this->allowedDoktypes) || isset($pages_row["sys_language_uid"]) ) && !in_array($pages_row[$id_name], $this->excludedPages) && !$pages_row['deleted']) {
			$langID = (isset($pages_row["sys_language_uid"])) ? $pages_row["sys_language_uid"] : $this->sys_language_uid;

			$link = (substr($link, 0, 1) == '/') ? substr($link, 1) : $link;
			if ($this->languageParamIf0 || $langID != 0) {
				$linkParams[$this->languageVar] = $langID;
			}

			$link = str_replace('?&amp;', '?', htmlspecialchars(utf8_encode($this->pi_getPageLink($pages_row[$id_name], $pages_row['target'], $linkParams))));
			$linkParams = array();


			$time = ($pages_row['SYS_LASTCHANGED'] > $pages_row['tstamp']) ? $pages_row['SYS_LASTCHANGED'] : $pages_row['tstamp'];

			$string = "<url>\n";
			$string.= "   <loc>{$this->url}" . $link . "</loc>\n";
			$string.= "   <lastmod>" . gmdate("Y-m-d\TH:i:s\Z", $time) . "</lastmod>\n";


			$priority = ($this->data[$pages_row[$id_name]][1] ? $this->data[$pages_row[$id_name]][1] : "0.5");

			$string .= "   <priority>{$priority}</priority>\n";

			if ($this->data[$pages_row[$id_name]][2]) {
				$string .= "   <changefreq>" . $this->data[$pages_row[$id_name]][2] . "</changefreq>\n";
			}

			if (!$this->data[$pages_row[$id_name]][2]) {
				// check if cache period set
				if ($pages_row['cache_timeout'] > 0 && $period = $this->mapTimeout2period($pages_row['cache_timeout'])) {
					$string .= "   <changefreq>" . $period . "</changefreq>\n";
				}
			}

			$string .= "</url>\n";
			$this->tRows[] = $string;
			$this->usedSites[] = $pages_row[$id_name];
		}
	}

	function getData() {
		$res = $this->db->exec_SELECTquery("*", "tx_weeaargooglesitemap", "1=1");
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$this->data[$row['pid']] = array($row['disabled'], $row['priority'], $row['changefreq']);
		}

		// check if configured tt_news single view pages should be excluded or
		// kept
		if (is_array($this->conf["tt_news."]["single_page."]) && count($this->conf["tt_news."]["single_page."]) > 0) {
			$singlePagesConfig = $this->conf["tt_news."]["single_page."];
			foreach ($singlePagesConfig as $key => $singlePage) {
				$isSinglePageConfigLeafNode = (strpos($key, ".") === false);

				// single pages are configured on parent level of detailed
				// configuration, so we trigger on leaf nodes on first level
				// beneath single_page
				if ($isSinglePageConfigLeafNode) {
					$shouldBeExcluded = true;

					$hasSubConfig = array_key_exists($key.'.', $singlePagesConfig);
					if ($hasSubConfig) {
						$shouldBeExcluded = !(array_key_exists('keepListedAsPage', $singlePagesConfig[$key.'.']) && ($singlePagesConfig[$key.'.']['keepListedAsPage'] != 0));
					}

					if ($shouldBeExcluded) {
						$this->excludedPages[] = $singlePage;
					}
				}
			}
		}
	}

	function mapTimeout2Period($sec) {
		switch ($sec) {
			case 60:
			case 300:
			case 900:
			case 1800:
				return "always";
				break;
			case 3600:
			case 14400:
				return "hourly";
				break;
			case 86400:
			case 172800:
				return "daily";
				break;
			case 604800:
				return "weekly";
				break;
			case 2678400:
				return "monthly";
				break;
		}

		return false;
	}

	function getHeader() {
		switch ($this->defaultCode) {
			case "sitemap_org":
				if ($this->useNewsSchema == 1) {
					return "<urlset \n\t\t xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" \n\t\txmlns:news=\"http://www.google.com/schemas/sitemap-news/0.9\">";
				} else {
					return "<urlset \n\t\txmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";
				}
				break;
			default:
				if ($this->useNewsSchema == 1) {
					return "<urlset 
										xmlns=\"http://www.google.com/schemas/sitemap/0.84\"
										xmlns:news=\"http://www.google.com/schemas/sitemap-news/0.9\"
										xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
										xsi:schemaLocation=\"http://www.google.com/schemas/sitemap/0.84 http://www.google.com/schemas/sitemap/0.84/sitemap.xsd\">";
				}
				return "<urlset 
									xmlns=\"http://www.google.com/schemas/sitemap/0.84\"
									xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
									xsi:schemaLocation=\"http://www.google.com/schemas/sitemap/0.84 http://www.google.com/schemas/sitemap/0.84/sitemap.xsd\">";

				break;
		}
	}

	function give_domain() {
		$url = $this->conf['domain'] ? $this->conf['domain'] : $_SERVER["SERVER_NAME"];

		if (substr($url, 0, 7) != "http://") {
			$url = "http://" . $url;
		}

		if (substr($url, strlen($url) - 1, 1) != "/") {
			$url = $url . "/";
		}

		return $url;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/weeaar_googlesitemap/pi1/class.tx_weeaargooglesitemap_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/weeaar_googlesitemap/pi1/class.tx_weeaargooglesitemap_pi1.php']);
}
?>
