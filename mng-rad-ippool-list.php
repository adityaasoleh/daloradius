<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@enginx.com> All Rights Reserved.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *********************************************************************************************************
 *
 * Authors:    Liran Tal <liran@enginx.com>
 *             Filippo Lauria <filippo.lauria@iit.cnr.it>
 *
 *********************************************************************************************************
 */

    include("library/checklogin.php");
    $operator = $_SESSION['operator_user'];

    include_once("lang/main.php");
    include_once("library/validation.php");
    include("library/layout.php");
    
    // init logging variables
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    // set session's page variable
    $_SESSION['PREV_LIST_PAGE'] = $_SERVER['REQUEST_URI'];

    $cols = array(
                    "id" => t('all','ID'),
                    "pool_name" => t('all','PoolName'),
                    "framedipaddress" => t('all','IPAddress'),
                    "nasipaddress" => t('all','NASIPAddress'),
                    "CalledStationId" => t('all','CalledStationId'),
                    "CallingStationID" => t('all','CallingStationID'),
                    "expiry_time" => t('all','ExpiryTime'),
                    "username" => t('all','Username'),
                    "pool_key" => t('all','PoolKey')
                 );
    $colspan = count($cols);
    $half_colspan = intval($colspan / 2);
                 
    $param_cols = array();
    foreach ($cols as $k => $v) { if (!is_int($k)) { $param_cols[$k] = $v; } }
    
    // whenever possible we use a whitelist approach
    $orderBy = (array_key_exists('orderBy', $_GET) && isset($_GET['orderBy']) &&
                in_array($_GET['orderBy'], array_keys($param_cols)))
             ? $_GET['orderBy'] : array_keys($param_cols)[0];

    $orderType = (array_key_exists('orderType', $_GET) && isset($_GET['orderType']) &&
                  in_array(strtolower($_GET['orderType']), array( "desc", "asc" )))
               ? strtolower($_GET['orderType']) : "asc";
    

    // print HTML prologue
    $title = t('Intro','mngradippoollist.php');
    $help = t('helpPage','mngradippoollist');
    
    print_html_prologue($title, $langCode);

    include("menu-mng-rad-ippool.php");
    
    echo '<div id="contentnorightbar">';
    print_title_and_help($title, $help);

    include('library/opendb.php');
    include('include/management/pages_common.php');

    // we use this simplified query just to initialize $numrows
    $sql = sprintf("SELECT COUNT(id) FROM %s", $configValues['CONFIG_DB_TBL_RADIPPOOL']);
    $res = $dbSocket->query($sql);
    $numrows = $res->fetchrow()[0];

    if ($numrows > 0) {
        /* START - Related to pages_numbering.php */
        
        // when $numrows is set, $maxPage is calculated inside this include file
        include('include/management/pages_numbering.php');    // must be included after opendb because it needs to read
                                                              // the CONFIG_IFACE_TABLES_LISTING variable from the config file
        
        // here we decide if page numbers should be shown
        $drawNumberLinks = strtolower($configValues['CONFIG_IFACE_TABLES_LISTING_NUM']) == "yes" && $maxPage > 1;
        
        /* END */
                     
        // we execute and log the actual query
        $sql = sprintf("SELECT id, pool_name, framedipaddress, nasipaddress, calledstationid,
                               callingstationid, expiry_time, username, pool_key
                          FROM %s", $configValues['CONFIG_DB_TBL_RADIPPOOL']);
        $sql .= sprintf(" ORDER BY %s %s LIMIT %s, %s", $orderBy, $orderType, $offset, $rowsPerPage);
        $res = $dbSocket->query($sql);
        $logDebugSQL = "$sql;\n";
        
        $per_page_numrows = $res->numRows();
        
        // this can be passed as form attribute and 
        // printTableFormControls function parameter
        $action = "mng-rad-ippool-del.php";
?>
<form name="listall" method="POST" action="<?= $action ?>">

    <table border="0" class="table1">
        <thead>

<?php
        // page numbers are shown only if there is more than one page
        if ($drawNumberLinks) {
            echo '<tr style="background-color: white">';
            printf('<td style="text-align: left" colspan="%s">go to page: ', $colspan);
            setupNumbering($numrows, $rowsPerPage, $pageNum, $orderBy, $orderType);
            echo '</td>' . '</tr>';
        }
?>
            <tr>
                <th style="text-align: left" colspan="<?= $colspan ?>">
<?php
        printTableFormControls('item[]', $action);
?>
                </th>
            </tr>

            <tr>
<?php
        // second line of table header
        printTableHead($cols, $orderBy, $orderType);
?>
            </tr>
            
        </thead>
        
        <tbody>
<?php
        $li_style = 'margin: 7px auto';
        
        // prepare table rows
        $table_rows = array();
        
        $count = 1;
        
        while ($row = $res->fetchRow()) {
            
            $tr = array();
            
            list($id, $pool_name, $framedipaddress, $nasipaddress, $calledstationid,
                 $callingstationid, $expiry_time, $username, $pool_key) = $row;
            
            // preparing checkbox
            $id = intval($id);
            $item_id = sprintf("ippool-%d", $id);
            $checkbox_id = sprintf("checkbox-%d", $count);
            
            // tooltip stuff
            $tooltipText = '<ul style="list-style-type: none">'
                     . sprintf('<li style="%s">', $li_style)
                     . sprintf('<a class="toolTip" href="mng-rad-ippool-edit.php?item=%s">%s</a></li>',
                               $item_id, t('Tooltip','EditIPAddress'))
                     . sprintf('<li style="%s">', $li_style)
                     . sprintf('<a class="toolTip" href="mng-rad-ippool-del.php?item=%s">%s</a></li>',
                               $item_id, t('Tooltip','RemoveIPAddress'))
                     . '</ul>';
                     
            $onclick = 'javascript:return false;';
            
            $tr[] = sprintf('<input type="checkbox" name="item[]" value="%s" id="%s">', $item_id, $checkbox_id)
                          . sprintf('<label for="%s">', $checkbox_id)
                          . sprintf('<a class="tablenovisit" href="#" onclick="%s" ' . "tooltipText='%s'>", $onclick, $tooltipText)
                          . $id . '</a>' . '</label>';
            
            // other row elements
            $tr[] = htmlspecialchars($pool_name, ENT_QUOTES, 'UTF-8');
            $tr[] = htmlspecialchars($framedipaddress, ENT_QUOTES, 'UTF-8');
            $tr[] = htmlspecialchars($nasipaddress, ENT_QUOTES, 'UTF-8');
            $tr[] = htmlspecialchars($calledstationid, ENT_QUOTES, 'UTF-8');
            $tr[] = htmlspecialchars($callingstationid, ENT_QUOTES, 'UTF-8');
            $tr[] = htmlspecialchars($expiry_time, ENT_QUOTES, 'UTF-8');
            $tr[] = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $tr[] = htmlspecialchars($pool_key, ENT_QUOTES, 'UTF-8');

            $table_rows[] = $tr;

            $count++;

        }
        
        // draw tr(s)
        $simple_td_format = '<td>%s</td>' . "\n";

        foreach ($table_rows as $tr) {
            echo '<tr>';
            
            foreach ($tr as $td) {
                printf($simple_td_format, $td);
            }
            
            echo '</tr>';
        }
?>        
        </tbody>
        
<?php
        $links = setupLinks_str($pageNum, $maxPage, $orderBy, $orderType);
        printTableFoot($per_page_numrows, $numrows, $colspan, $drawNumberLinks, $links);
?>
        
    </table>
    
    <input type="hidden" name="csrf_token" value="<?= dalo_csrf_token() ?>">
    
</form>

<?php
    } else {
        $failureMsg = "Nothing to display";
        include_once("include/management/actionMessages.php");
    }
    
    include('library/closedb.php');

    include('include/config/logging.php');
    
    $inline_extra_js = "
var tooltipObj = new DHTMLgoodies_formTooltip();
tooltipObj.setTooltipPosition('right');
tooltipObj.setPageBgColor('#EEEEEE');
tooltipObj.setTooltipCornerSize(15);
tooltipObj.initFormFieldTooltip()";
    
    print_footer_and_html_epilogue($inline_extra_js);
?>
