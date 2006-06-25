<?php
/**
* $Id: Link.php,v 1.2 2006-06-25 15:46:36 matteo Exp $
*
* Link management - Functions and Classes
*
* @author       Matteo Scaramuccia <matteo@scaramuccia.com>
* @since        2005-11-02
* @copyright    (c) 2005 phpMyFAQ Team
*
* The contents of this file are subject to the Mozilla Public License
* Version 1.1 (the "License"); you may not use this file except in
* compliance with the License. You may obtain a copy of the License at
* http://www.mozilla.org/MPL/
*
* Software distributed under the License is distributed on an "AS IS"
* basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
* License for the specific language governing rights and limitations
* under the License.
*/

// {{{ Includes
/**
 * This include is needed for accessing to mod_rewrite support configuration value
 */
require_once('config.php');
// }}}

// {{{ Constants
/**#@+
  * General Link definitions
  */
define('PMF_LINK_SEARCHPART_SEPARATOR', '?');
define('PMF_LINK_AMPERSAND', '&amp;');
define('PMF_LINK_EQUAL', '=');
define('PMF_LINK_SLASH', '/');
define('PMF_LINK_CONTENT', 'content/');
define('PMF_LINK_CATEGORY', 'category/');
define('PMF_LINK_HTML_MINUS', '-');
define('PMF_LINK_HTML_UNDERSCORE', '_');
define('PMF_LINK_HTML_SLASH', '/');
define('PMF_LINK_HTML_TARGET_BLANK', '_blank');
define('PMF_LINK_HTML_TARGET_PARENT', '_parent');
define('PMF_LINK_HTML_TARGET_SELF', '_self');
define('PMF_LINK_HTML_TARGET_TOP', '_top');
/**#@-*/
/**#@+
  * System pages definitions
  */
define('PMF_LINK_INDEX_ADMIN', '/admin/index.php');
define('PMF_LINK_INDEX_HOME', '/index.php');
/**#@-*/
/**#@+
  * System GET keys definitions
  */
define('PMF_LINK_GET_ACTION', 'action');
define('PMF_LINK_GET_LANG', 'lang');
define('PMF_LINK_GET_ARTLANG', 'artlang');
define('PMF_LINK_GET_CATEGORY', 'cat');
define('PMF_LINK_GET_ID', 'id');
define('PMF_LINK_GET_LETTER', 'letter');
define('PMF_LINK_GET_PAGE', 'seite');
define('PMF_LINK_GET_HIGHLIGHT', 'highlight');
/**#@-*/
/**#@+
  * System GET values definitions
  */
define('PMF_LINK_GET_ACTION_ADD', 'add');
define('PMF_LINK_GET_ACTION_ARTIKEL', 'artikel');
define('PMF_LINK_GET_ACTION_ASK', 'ask');
define('PMF_LINK_GET_ACTION_CONTACT', 'contact');
define('PMF_LINK_GET_ACTION_HELP', 'help');
define('PMF_LINK_GET_ACTION_OPEN', 'open');
define('PMF_LINK_GET_ACTION_SEARCH', 'search');
define('PMF_LINK_GET_ACTION_SITEMAP', 'sitemap');
define('PMF_LINK_GET_ACTION_SHOW', 'show');
/**#@-*/
/**#@+
  * Modrewrite virtual pages: w/o extension due to concatenated parameters
  */
define('PMF_LINK_HTML_CATEGORY', 'category');
define('PMF_LINK_HTML_EXTENSION', '.html');
define('PMF_LINK_HTML_SITEMAP', 'sitemap');
/**#@-*/
/**#@+
  * Modrewrite virtual pages: w/ extension
  */
define('PMF_LINK_HTML_ADDCONTENT', 'addcontent.html');
define('PMF_LINK_HTML_ASK', 'ask.html');
define('PMF_LINK_HTML_CONTACT', 'contact.html');
define('PMF_LINK_HTML_HELP', 'help.html');
define('PMF_LINK_HTML_OPEN', 'open.html');
define('PMF_LINK_HTML_SEARCH', 'search.html');
define('PMF_LINK_HTML_SHOWCAT', 'showcat.html');
/**#@-*/
// }}}

// {{{ Functions
function getLinkHtmlAnchor($url, $text = null, $target = null)
{
    $link = new PMF_Link($url, $text, $target);
    return $link->toHtmlAnchor();
}

function getLinkString($url, $forceNoModrewriteSupport = false, $text = null, $target = null)
{
    $link = new PMF_Link($url, $text, $target);
    return $link->toString($forceNoModrewriteSupport);
}

function getLinkUri($url, $text = null, $target = null)
{
    $link = new PMF_Link($url, $text, $target);
    return $link->toUri();
}
// }}}

// {{{ Classes
/**
 * PMF_Link Class
 *
 * This class wrap the needs for managing an HTML anchor
 * taking into account also the HTML anchor creation
 * with specific handling for mod_rewrite PMF native support
 */
class PMF_Link
{
    // {{{ Class properties specific to an HTML link anchor
    var $url     = '';
    var $class   = '';
    var $text   = '';
    var $tooltip = '';
    var $target  = '';
    var $name    = '';
    // }}}
    // {{{ Class properties specific to the SEO/SEF URLs
    var $itemTitle = '';
    // }}}

    function PMF_Link($url, $text = null, $target = null)
    {
        $this->url = $url;
        $this->text = $text;
        if ( (!isset($text)) || (empty($text)) ) {
            $this->title = '';
        }
        $this->target = $target;
        if ( (!isset($target)) || (empty($target)) ) {
            $this->target = '';
        }
        $this->class   = '';
        $this->tooltip = '';
        $this->name    = '';

        $this->itemTitle = '';
    }

    function isIISServer()
    {
        return (isset($_SERVER['ALL_HTTP']));
    }

    function isAdminIndex()
    {
        if (!$this->isSystemLink()) {
            return false;
        }

        return !(false === strpos($this->url, PMF_LINK_INDEX_ADMIN));
    }

    function isHomeIndex()
    {
        if (!$this->isSystemLink()) {
            return false;
        }

        return !(false === strpos($this->url, PMF_LINK_INDEX_HOME));
    }

    function isInternalReference()
    {
        if ($this->isRelativeSystemLink()) {
            return true;
        }
        if (false === strpos($this->url, '#')) {
            return false;
        }

        return (strpos($this->url, '#') == 0);
    }

    function isRelativeSystemLink()
    {
        $slashIdx = strpos($this->url, PMF_LINK_SLASH);
        if (false === $slashIdx) {
            return false;
        }

        return ($slashIdx == 0);
    }

    function isSystemLink()
    {
        // a. Is the url relative, starting with '/'?
        // b. Is the url related to the current running PMF system?
        if ($this->isRelativeSystemLink()) {
            return true;
        }
        // $_SERVER['HTTP_HOST'] is the name of the website or virtual host name
        return !(false === strpos($this->url, $_SERVER['HTTP_HOST']));
    }

    function hasModRewriteSupport()
    {
        global $PMF_CONF;

        return ((isset($PMF_CONF['mod_rewrite'])) && ($PMF_CONF['mod_rewrite'] == 'TRUE'));
    }

    function hasScheme()
    {
        $parsed = parse_url($this->url);

        return (!empty($parsed['scheme']));
    }

    function getSEOItemTitle()
    {
        $itemTitle = $this->itemTitle;
        // Use a '_' for the words separation
        $itemTitle = str_replace(' ', '_', $itemTitle);
        // Hack: double slash enconding: / -> %2F -> %252F
        $itemTitle = str_replace('/', '%252F', $itemTitle);

        return urlencode($itemTitle);
    }

    function getHttpGetParameters()
    {
        $query = $this->getQuery();
        $parameters  = array();

        if (!empty($query))
        {
            $params = explode(PMF_LINK_AMPERSAND, $query);
            foreach ($params as $param)
            {
                if (!empty($param))
                {
                    $couple = explode(PMF_LINK_EQUAL, $param);
                    list($key, $val) = $couple;
                    $parameters[$key] = urldecode($val);
                }
            }
        }
        
        return $parameters;
    }

    function getPage()
    {
        $page = '';
        if (!empty($this->url)) {
            $parsed = parse_url($this->url);
            // Take the last element
            $page = substr(strrchr($parsed['path'], PMF_LINK_SLASH), 1);
        }

        return $page;
    }

    function getQuery()
    {
        $query = '';
        if (!empty($this->url)) {
            $parsed = parse_url($this->url);
            if (isset($parsed['query'])) {
                $query = $parsed['query'];
            }
        }

        return $query;
    }

    function getDefaultScheme()
    {
        $scheme = 'http://';
        if ($this->isSystemLink()) {
            $scheme = PMF_Link::getSystemScheme();
        }

        return $scheme;
    }

    function getSystemScheme()
    {
        $scheme = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://';

        return $scheme;
    }

    function getSystemRelativeUri($path = null)
    {
        if (isset($path)) {
            return str_replace($path, '', $_SERVER['PHP_SELF']);
        }

        return str_replace('/inc/Link.php', '', $_SERVER['PHP_SELF']);
    }

    function getSystemUri()
    {
        // $_SERVER['HTTP_HOST'] is the name of the website or virtual host name
        $sysUri = PMF_Link::getSystemScheme().$_SERVER['HTTP_HOST'];
        if (($_SERVER['SERVER_PORT'] != '80') && ($_SERVER['SERVER_PORT'] != '443')) {
            $sysUri .= ':'.$_SERVER['SERVER_PORT'];
        }

        return $sysUri.PMF_link::getSystemRelativeUri();
    }

    function toHtmlAnchor()
    {
        // Sanitize the provided url
        $url = $this->toString();
        // Prepare HTML anchor element
        $htmlAnchor = '<a';
        if (!empty($this->class)) {
            $htmlAnchor .= ' class="'.$this->class.'"';
        }
        if (!empty($this->tooltip)) {
            $htmlAnchor .= ' title="'.$this->tooltip.'"';
        }
        if (!empty($this->name)) {
                $htmlAnchor .= ' name="'.$this->name.'"';
        } else {
            if (!empty($this->url)) {
                $htmlAnchor .= ' href="'.$url.'"';
            }
            if (!empty($this->target)) {
                $htmlAnchor .= ' target="'.$this->target.'"';
            }
        }
        $htmlAnchor .= '>';
        if (!empty($this->text)) {
            $htmlAnchor .= $this->text;
        } else {
            if (!empty($this->name)) {
                $htmlAnchor .= $this->name;
            } else {
                $htmlAnchor .= $url;
            }
        }
        $htmlAnchor .= '</a>';

        return $htmlAnchor;
    }

    function toString($forceNoModrewriteSupport = false)
    {
        $url = $this->toUri();
        // Check mod_rewrite support and 'rewrite' the passed (system) uri
        // according to the rewrite rules written in .htaccess
        if ((!$forceNoModrewriteSupport) && ($this->hasModRewriteSupport())) {
            if ($this->isHomeIndex()) {
                $getParams = $this->getHttpGetParameters();
                if (isset($getParams[PMF_LINK_GET_ACTION])) {
                    // Get the part of the url 'till the '/' just before the pattern
                    $url = substr($url, 0, strpos($url, PMF_LINK_INDEX_HOME) + 1);
                    // Build the Url according to .htaccess rules
                    switch($getParams[PMF_LINK_GET_ACTION]) {
                        case PMF_LINK_GET_ACTION_ADD:
                            $url .= PMF_LINK_HTML_ADDCONTENT;
                            break;
                        case PMF_LINK_GET_ACTION_ARTIKEL:
                            // TODO: Remove the check below WHEN _httpd.ini will be aligned and tested.
                            if ($this->isIISServer()) {
                                $url .= $getParams[PMF_LINK_GET_CATEGORY].PMF_LINK_HTML_UNDERSCORE.$getParams[PMF_LINK_GET_ID].PMF_LINK_HTML_UNDERSCORE.$getParams[PMF_LINK_GET_ARTLANG].PMF_LINK_HTML_EXTENSION;
                            } else {
                                $url .= PMF_LINK_CONTENT.$getParams[PMF_LINK_GET_CATEGORY].PMF_LINK_HTML_SLASH.$getParams[PMF_LINK_GET_ID].PMF_LINK_HTML_SLASH.$getParams[PMF_LINK_GET_ARTLANG].PMF_LINK_SLASH.$this->getSEOItemTitle().PMF_LINK_HTML_EXTENSION;
                            }
                            if (isset($getParams[PMF_LINK_GET_HIGHLIGHT])) {
                                $url .= PMF_LINK_SEARCHPART_SEPARATOR.PMF_LINK_GET_HIGHLIGHT.'='.$getParams[PMF_LINK_GET_HIGHLIGHT];
                            }
                            break;
                        case PMF_LINK_GET_ACTION_ASK:
                            $url .= PMF_LINK_HTML_ASK;
                            break;
                        case PMF_LINK_GET_ACTION_CONTACT:
                            $url .= PMF_LINK_HTML_CONTACT;
                            break;
                        case PMF_LINK_GET_ACTION_HELP:
                            $url .= PMF_LINK_HTML_HELP;
                            break;
                        case PMF_LINK_GET_ACTION_OPEN:
                            $url .= PMF_LINK_HTML_OPEN;
                            break;
                        case PMF_LINK_GET_ACTION_SEARCH:
                            $url .= PMF_LINK_HTML_SEARCH;
                            break;
                        case PMF_LINK_GET_ACTION_SITEMAP:
                            $url .= PMF_LINK_HTML_SITEMAP.PMF_LINK_HTML_MINUS.$getParams[PMF_LINK_GET_LETTER].PMF_LINK_HTML_UNDERSCORE.$getParams[PMF_LINK_GET_LANG].PMF_LINK_HTML_EXTENSION;
                            break;
                        case PMF_LINK_GET_ACTION_SHOW:
                            if (    !isset($getParams[PMF_LINK_GET_CATEGORY])
                                 || (isset($getParams[PMF_LINK_GET_CATEGORY]) && (0 == $getParams[PMF_LINK_GET_CATEGORY]))
                                ) {
                                $url .= PMF_LINK_HTML_SHOWCAT;
                            }
                            else {
                                // TODO: Remove the check below WHEN _httpd.ini will be aligned and tested.
                                if ($this->isIISServer()) {
                                    $url .= PMF_LINK_HTML_CATEGORY.$getParams[PMF_LINK_GET_CATEGORY];
                                    if (isset($getParams[PMF_LINK_GET_PAGE])) {
                                        $url .= PMF_LINK_HTML_UNDERSCORE.$getParams[PMF_LINK_GET_PAGE];
                                    }
                                    $url .= PMF_LINK_HTML_EXTENSION;
                                } else {
                                    $url .= PMF_LINK_CATEGORY.$getParams[PMF_LINK_GET_CATEGORY];
                                    if (isset($getParams[PMF_LINK_GET_PAGE])) {
                                        $url .= PMF_LINK_HTML_SLASH.$getParams[PMF_LINK_GET_PAGE];
                                    }
                                    $url .= PMF_LINK_HTML_SLASH.$this->getSEOItemTitle().PMF_LINK_HTML_EXTENSION;
                                }
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        return $url;
    }

    function toUri()
    {
        $url = $this->url;
        if (!empty($url)) {
            if ((!$this->hasScheme()) && (!$this->isInternalReference())) {
                // Manage an URI without a Scheme BUT NOT those that are 'internal' references
                $url = $this->getDefaultScheme().$this->url;
            }
        }
        
        return $url;
    }
}
// }}}
?>
