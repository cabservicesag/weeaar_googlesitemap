<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006 Marcel Alburg <alb@weeaar.com>
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
***************************************************************/


	// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require_once('conf.php');
require_once($BACK_PATH.'init.php');
require_once($BACK_PATH.'template.php');

$LANG->includeLLFile('EXT:weeaar_googlesitemap/mod1/locallang.xml');
require_once(PATH_t3lib.'class.t3lib_scbase.php');
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.
	// DEFAULT initialization of a module [END]



/**
 * Module 'GoogleSitemap' for the 'weeaar_googlesitemap' extension.
 *
 * @author	Marcel Alburg <alb@weeaar.com>
 * @package	TYPO3
 * @subpackage	tx_weeaargooglesitemap
 */
class  tx_weeaargooglesitemap_module1 extends t3lib_SCbase {
				var $pageinfo;

				var $data = Array();

				/**
				 * Initializes the Module
				 * @return	void
				 */
				function init()	{
					global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

					parent::init();

					/*
					if (t3lib_div::_GP('clear_all_cache'))	{
						$this->include_once[] = PATH_t3lib.'class.t3lib_tcemain.php';
					}
					*/
				}

				/**
				 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
				 *
				 * @return	void
				 */
				function menuConfig()	{
					global $LANG;
					$this->MOD_MENU = Array (
						'function' => Array (
							'1' => $LANG->getLL('siteconfiguration'),
#							'2' => $LANG->getLL('function2'),
#							'3' => $LANG->getLL('function3'),
						)
					);
					parent::menuConfig();
				}

				/**
				 * Main function of the module. Write the content to $this->content
				 * If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
				 *
				 * @return	[type]		...
				 */
				function main()	{
					global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

					// Access check!
					// The page will show only if there is a valid page and if this page may be viewed by the user
					$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
					$access = is_array($this->pageinfo) ? 1 : 0;

					if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id))	{

							// Draw the header.
						$this->doc = t3lib_div::makeInstance('mediumDoc');
						$this->doc->backPath = $BACK_PATH;
						$this->doc->form='<form action="" method="POST">';

							// JavaScript
						$this->doc->JScode = '
							<script language="javascript" type="text/javascript">
								script_ended = 0;
								function jumpToUrl(URL)	{
									document.location = URL;
								}
							</script>
						';
						$this->doc->postCode='
							<script language="javascript" type="text/javascript">
								script_ended = 1;
								if (top.fsMod) top.fsMod.recentIds["web"] = 0;
							</script>
						';

						$headerSection = $this->doc->getHeader('pages',$this->pageinfo,$this->pageinfo['_thePath']).'<br />'.$LANG->sL('LLL:EXT:lang/locallang_core.xml:labels.path').': '.t3lib_div::fixed_lgd_pre($this->pageinfo['_thePath'],50);

						$this->content.=$this->doc->startPage($LANG->getLL('title'));
						$this->content.=$this->doc->header($LANG->getLL('title'));
						$this->content.=$this->doc->spacer(5);
						$this->content.=$this->doc->section('',$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,'SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function'])));
						$this->content.=$this->doc->divider(5);


						// Render content:
						$this->moduleContent();


						// ShortCut
						if ($BE_USER->mayMakeShortcut())	{
							$this->content.=$this->doc->spacer(20).$this->doc->section('',$this->doc->makeShortcutIcon('id',implode(',',array_keys($this->MOD_MENU)),$this->MCONF['name']));
						}

						$this->content.=$this->doc->spacer(10);
					} else {
							// If no access or if ID == zero

						$this->doc = t3lib_div::makeInstance('mediumDoc');
						$this->doc->backPath = $BACK_PATH;

						$this->content.=$this->doc->startPage($LANG->getLL('title'));
						$this->content.=$this->doc->header($LANG->getLL('title'));
						$this->content.=$this->doc->spacer(5);
						$this->content.=$this->doc->spacer(10);
					}
				}

				/**
				 * Prints out the module HTML
				 *
				 * @return	void
				 */
				function printContent()	{

					$this->content.=$this->doc->endPage();
					echo $this->content;
				}

				function saveSubmit()
				{
					$priority = t3lib_div::_GP('priority');
					$period = t3lib_div::_GP('period');
					$site = t3lib_div::_GP('site');


					foreach($priority as $uid => $val)
					{
						$deleted = ($site[$uid] == "on"?1:0);

						$where = "pid = ".$uid;
						$set =  array("disabled" => $deleted, "priority" => $val, "changefreq" => $period[$uid]);
						if ($this->data[$uid])
						{
							$this->db->exec_UPDATEquery("tx_weeaargooglesitemap",$where,$set);
						}
						else
						{
							$set["pid"] = $uid;
							$res = $this->db->exec_INSERTquery("tx_weeaargooglesitemap",$set);
						}
					}
				}

				function getData()
				{
					$res = $this->db->exec_SELECTquery("*", "tx_weeaargooglesitemap", "1=1");
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) 
					{
						$this->data[$row['pid']] = array($row['disabled'], $row['priority'], $row['changefreq']);
					}
				}

				/**
				 * Generates the module content
				 *
				 * @return	void
				 */
				function moduleContent()	{
					$this->db = $GLOBALS["TYPO3_DB"];

					switch((string)$this->MOD_SETTINGS['function'])	{
						case 1:
							$this->getData();

							if (t3lib_div::_GP('priority'))
							{
								$this->saveSubmit();
							}
							$this->getData();

							$this->showPageTree();
							break;
						case 2:
							$content='<div align=center><strong>Menu item #2...</strong></div>';
							$this->content.=$this->doc->section('Message #2:',$content,0,1);
							break;
						case 3:
							$content='<div align=center><strong>Menu item #3...</strong></div>';
							$this->content.=$this->doc->section('Message #3:',$content,0,1);
							break;
					}
				}

				function showPageTree() {
      		$siteRootUid= $this->id;
      		if (isset($fromRoot)) $siteRootUid= $this->getSiteRoot();

					if ($siteRootUid)
					{
						$pages = $this->getPageTreeOrder($siteRootUid);

						$this->content .= $this->getPageInfo($pages);
					}
				}

				function getPriority($uid, $wrap = "<option %s>|</option>")
				{
					$content = "";
					for ($i = 0; $i < 1; $i+=0.1)
					{
						if (strlen($this->data[$uid][1]) > 0)
						{

							// Workaround, because int compare doesn't work
							if (str_replace(".", ",", $this->data[$uid][1]) == str_replace(".", ",", $i))
							{
								$select = "selected";
							}
							else
							{
								$select = "";
							}
						}
						else
						{
							$select = ($i == 0.5?"selected":"");
						}

						$content .= str_replace("|", $i, sprintf($wrap, $select));
					}

					return $content;
				}

				function getPeriod($uid, $wrap = "<option %s>|</option>") 
				{
					$period = array('', 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never');

					foreach($period as $val)
					{
						$select = ($this->data[$uid][2] == $val?"selected":"");
						$content .= str_replace("|", $val, sprintf($wrap, $select));
					}

					return $content;
				}

				function getPageInfo($pages, $level = 0)
				{
					$allowed_doktype = array(2, 1, 4);

					$content = "<form action='' method='post'>";
					$content .= "<table>";
					$content .= "<tr><td><strong>Disabled</strong></td><td><strong>Doktype</strong></td><td><strong>Page ID</strong></td><td><strong>Title</strong></td><td><strong>Priority</strong></td><td><strong>Period</strong><td></tr>";
					for ($i=1; $i < count($pages); $i++)
					{
						$select = ($this->data[$pages[$i]['uid']][0] == 1?"checked":"");

						$content .= "<tr>";
						if (in_array($pages[$i]['doktype'], $allowed_doktype))
						{
							$content .= "<td><input type='checkbox' name='site[".$pages[$i]['uid']."]' ".$select."/></td>";
						}
						else
						{
							$content .= "<td>&nbsp;</td>";
						}
						$content .= "<td>".$pages[$i]['doktype']."</td>";
						$content .= "<td>".$pages[$i]['uid']."</td>";
						if (!isset($startLevel))
						{
							$startLevel = $pages[$i]['level'];
						}

						$content .= "<td>";

						if ($pages[($i+1)]['level'] != $pages[$i]['level'] && $pages[($i+1)]['level'] >= $startLevel)
						{
							for ($j = 2; $j < $pages[$i]['level']; $j++)
							{
								$content .= "<img src='img/sub1.gif'>";
							}
							$content .= "<img src='img/sub.gif'>";
						}
						else
						{
							for ($j = 1; $j < $pages[$i]['level']; $j++)
							{
								$content .= "<img src='img/sub1.gif'>";
							}
							$content .= "<img src='img/sub.gif'>";
						}
						$content .= $pages[$i]['title'];
						$content .= "</td>";
						if (in_array($pages[$i]['doktype'], $allowed_doktype))
						{
							$content .= "<td><select name=\"priority[".$pages[$i]['uid']."]\">".$this->getPriority($pages[$i]['uid'])."</select></td>";
							$content .= "<td><select name=\"period[".$pages[$i]['uid']."]\">".$this->getPeriod($pages[$i]['uid'])."</select></td>";
						}
						else
						{
							$content .= "<td>&nbsp;</td><td>&nbsp;</td>";
						}
						$content .= "</tr>";
					}
					$content .= "<tr><td colspan='4'><input type='submit'/></td></tr>";
					$content .= "</table>";
					$content .= "</form>";


					return $content;
				}

				 function getPagetreeOrder($siteRootUid) {
						$pages[]= array();
						$query= "deleted='0' AND hidden='0' ORDER BY sorting,pid";
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid, pid, sorting, title,doktype', 'pages', $query);
						while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
							$tmp= array(
								'uid'=>$row['uid'],
								'pid'=>$row['pid'],
								'title'=>$row['title'],
								'doktype'=>$row['doktype'],
								'additional'=>'0'
							);
							$pages[]= $tmp;
						}
						$startPid= $siteRootUid;
						$orderNum= -1;
						$order=array( array('uid'=>$siteRootUid, 'level'=>'0', 'title' => '', 'doktype' => '') );
						// l0:
						$l0=0; while($l0 < count($pages)) {
							 if ($pages[$l0]['uid']== $startPid) {
									$curL0Uid= $pages[$l0]['uid']; $order[]= array('uid'=>$curL0Uid,'level'=>'1', 'title' => $pages[$l0]['title'], 'doktype' => $pages[$l0]['doktype']);
										 //l1:
										 $l1=0; while($l1 < count($pages)) {
												if ($pages[$l1]['pid']== $curL0Uid) {
													 $curL1Uid= $pages[$l1]['uid'];   $order[]= array('uid'=>$curL1Uid,'level'=>'2', 'title' => $pages[$l1]['title'], 'doktype' => $pages[$l1]['doktype']);
													 //l2:
													 $l2=0; while($l2 < count($pages)) {
															if ($pages[$l2]['pid']== $curL1Uid) {
																 $curL2Uid= $pages[$l2]['uid']; $order[]= array('uid'=>$curL2Uid,'level'=>'3', 'title' => $pages[$l2]['title'], 'doktype' => $pages[$l2]['doktype']);
																 //l3:
																 $l3=0; while($l3 < count($pages)) {
																		if ($pages[$l3]['pid']== $curL2Uid) {
																			 $curL3Uid= $pages[$l3]['uid']; $order[]= array('uid'=>$curL3Uid,'level'=>'4', 'title' => $pages[$l3]['title'], 'doktype' => $pages[$l3]['doktype']);
																			 //l4:
																			 $l4=0; while($l4 < count($pages)) {
																					if ($pages[$l4]['pid']== $curL3Uid) {
																						 $curL4Uid= $pages[$l4]['uid']; $order[]= array('uid'=>$curL4Uid,'level'=>'5','title' => $pages[$l4]['title'], 'doktype' => $pages[$l4]['doktype']);
																						 //l5:
																						 $l5=0; while($l5 < count($pages)) {
																								if ($pages[$l5]['pid']== $curL4Uid) {
																									 $curL5Uid= $pages[$l5]['uid']; $order[]= array('uid'=>$curL5Uid,'level'=>'6','title'=>$pages[$l5]['title'], 'doktype' => $pages[$l5]['doktype']);
																								}
																								$l5++;
																						 } // end l5
																					}
																					$l4++;
																			 }   //end l4
																		}
																		$l3++;
																 }   //end l3
															}
															$l2++;
													 }   //end l2
												}
												$l1++;
										 } //end l1
							 }
							 $l0++;
						} //end l0
						return $order;
				 } // -------------------------------------------------------------------------

				function moduleContent2()	{
					switch((string)$this->MOD_SETTINGS['function'])	{
						case 1:
							$content='<div align=center><strong>Hello World!</strong></div><br />
								<br />This is the GET/POST vars sent to the script:<br />'.
								'GET:'.t3lib_div::view_array($_GET).'<br />'.
								'POST:'.t3lib_div::view_array($_POST).'<br />'.
								'';
							$this->content.=$this->doc->section('Message #1:',$content,0,1);
						break;
						case 2:
							$content='<div align=center><strong>Menu item #2...</strong></div>';
							$this->content.=$this->doc->section('Message #2:',$content,0,1);
						break;
						case 3:
							$content='<div align=center><strong>Menu item #3...</strong></div>';
							$this->content.=$this->doc->section('Message #3:',$content,0,1);
						break;
					}
				}
			}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/weeaar_googlesitemap/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/weeaar_googlesitemap/mod1/index.php']);
}




// Make instance:
$SOBE = t3lib_div::makeInstance('tx_weeaargooglesitemap_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>
