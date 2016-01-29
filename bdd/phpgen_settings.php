<?php

//  define('SHOW_VARIABLES', 1);
//  define('DEBUG_LEVEL', 1);

//  error_reporting(E_ALL ^ E_NOTICE);
//  ini_set('display_errors', 'On');

set_include_path('.' . PATH_SEPARATOR . get_include_path());


include_once dirname(__FILE__) . '/' . 'components/utils/system_utils.php';

//  SystemUtils::DisableMagicQuotesRuntime();

SystemUtils::SetTimeZoneIfNeed('Europe/Belgrade');

function GetGlobalConnectionOptions()
{
    return array(
  'server' => '192.168.10.50',
  'username' => 'toto',
  'password' => 'SeSame1234',
  'database' => 'bdd'
);
}

function HasAdminPage()
{
    return false;
}

function GetPageGroups()
{
    $result = array('Default');
    return $result;
}

function GetPageInfos()
{
    $result = array();
    $result[] = array('caption' => 'Dbo.EtatConnexion', 'short_caption' => 'Dbo.EtatConnexion', 'filename' => 'dbo.EtatConnexion.php', 'name' => 'dbo.EtatConnexion', 'group_name' => 'Default', 'add_separator' => false);
    $result[] = array('caption' => 'Dbo.EvenementSysteme', 'short_caption' => 'Dbo.EvenementSysteme', 'filename' => 'dbo.EvenementSysteme.php', 'name' => 'dbo.EvenementSysteme', 'group_name' => 'Default', 'add_separator' => false);
    $result[] = array('caption' => 'Dbo.InfosFichiers', 'short_caption' => 'Dbo.InfosFichiers', 'filename' => 'dbo.InfosFichiers.php', 'name' => 'dbo.InfosFichiers', 'group_name' => 'Default', 'add_separator' => false);
    $result[] = array('caption' => 'Dbo.IPClients', 'short_caption' => 'Dbo.IPClients', 'filename' => 'dbo.IPClients.php', 'name' => 'dbo.IPClients', 'group_name' => 'Default', 'add_separator' => false);
    $result[] = array('caption' => 'Dbo.MachinesClientes', 'short_caption' => 'Dbo.MachinesClientes', 'filename' => 'dbo.MachinesClientes.php', 'name' => 'dbo.MachinesClientes', 'group_name' => 'Default', 'add_separator' => false);
    return $result;
}

function GetPagesHeader()
{
    return
    '';
}

function GetPagesFooter()
{
    return
        ''; 
    }

function ApplyCommonPageSettings(Page $page, Grid $grid)
{
    $page->SetShowUserAuthBar(false);
    $page->OnCustomHTMLHeader->AddListener('Global_CustomHTMLHeaderHandler');
    $page->OnGetCustomTemplate->AddListener('Global_GetCustomTemplateHandler');
    $grid->BeforeUpdateRecord->AddListener('Global_BeforeUpdateHandler');
    $grid->BeforeDeleteRecord->AddListener('Global_BeforeDeleteHandler');
    $grid->BeforeInsertRecord->AddListener('Global_BeforeInsertHandler');
}

/*
  Default code page: 1252
*/
function GetAnsiEncoding() { return 'windows-1252'; }

function Global_CustomHTMLHeaderHandler($page, &$customHtmlHeaderText)
{

}

function Global_GetCustomTemplateHandler($part, $mode, &$result, &$params, Page $page = null)
{

}

function Global_BeforeUpdateHandler($page, &$rowData, &$cancel, &$message, $tableName)
{

}

function Global_BeforeDeleteHandler($page, &$rowData, &$cancel, &$message, $tableName)
{

}

function Global_BeforeInsertHandler($page, &$rowData, &$cancel, &$message, $tableName)
{

}

function GetDefaultDateFormat()
{
    return 'Y-m-d';
}

function GetFirstDayOfWeek()
{
    return 0;
}

function GetEnableLessFilesRunTimeCompilation()
{
    return false;
}



?>