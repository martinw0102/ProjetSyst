<?php

include_once dirname(__FILE__) . '/' . 'engine.php';
include_once dirname(__FILE__) . '/' . 'database_engine_utils.php';

class MsConnectionFactory extends ConnectionFactory {
    public function DoCreateConnection($AConnectionParams) {
        return new MsConnection($AConnectionParams);
    }

    public function CreateDataset($AConnection, $sql) {
        return new MsDataReader($AConnection, $sql);
    }

    public function CreateEngCommandImp() {
        return new MsSQLCommandImp($this);
    }
}

class MsCOMConnectionFactory extends ConnectionFactory {
    public function DoCreateConnection($AConnectionParams) {
        return new MsCOMConnection($AConnectionParams);
    }

    public function CreateDataset($AConnection, $sql) {
        return new MsCOMDataReader($AConnection, $sql);
    }

    public function CreateEngCommandImp() {
        return new MsSQLCommandImp($this);
    }
}

class SqlSrvConnectionFactory extends ConnectionFactory {
    public function DoCreateConnection($AConnectionParams) {
        return new SqlSrvConnection($AConnectionParams);
    }

    public function CreateDataset($AConnection, $sql) {
        return new SqlSrvDataReader($AConnection, $sql);
    }

    public function CreateEngCommandImp() {
        return new MsSQLCommandImp($this);
    }
}

class MsSQLCommandImp extends EngCommandImp {

    public function GetFirstQuoteChar() {
        return '[';
    }

    public function GetLastQuoteChar() {
        return ']';
    }

    public function GetDateTimeFieldValueAsSQL($fieldInfo, $value) {
        return sprintf('CONVERT(DATETIME, \'%s\', 120)', $value->ToString('Y-m-d H:i:s'));
    }


    protected function GetDateFieldValueAsSQL($fieldInfo, $value) {
        return sprintf('CONVERT(DATETIME, \'%s\', 120)', $value->ToString('Y-m-d H:i:s'));
    }

    public function GetCastToCharExpression($value, $fieldInfo) {
        if ($this->GetServerVersion()->IsServerVersion(9))
            return sprintf("CAST(%s AS VARCHAR(max))", $value);
        else
            return sprintf("CAST(%s AS VARCHAR(8000))", $value);
    }

    protected function GetBlobFieldValueAsSQL($value) {
        if (is_array($value)) {
            return '0x' . bin2hex(file_get_contents($value[0]));
        } else {
            return '0x' . bin2hex($value);
        }
    }

    public function GetFieldValueAsSQL($fieldInfo, $value) {
        if ($fieldInfo->FieldType == ftString) {
            return "N".parent::GetFieldValueAsSQL($fieldInfo, $value);
        }
        else
            return parent::GetFieldValueAsSQL($fieldInfo, $value);
    }

    private function AddFieldAliasToModifiedSelectField(&$result, $fieldInfo) {
        if (isset($fieldInfo->Alias) && $fieldInfo->Alias != '')
            $result .= ' AS ' . $this->QuoteIdentifier($fieldInfo->Alias);
        else
            $result .= ' AS ' . $this->QuoteIdentifier($fieldInfo->Name);
    }

    protected function GetDateTimeFieldAsSQLForSelect($fieldInfo) {
        $result = sprintf('CONVERT(VARCHAR, %s, 120)', $this->GetFieldFullName($fieldInfo));
        return $result;
    }

    public function GetLimitClause($limitCount, $upLimit) {
        return '';
    }

    private function ApplyCommandLimitsToDataset($command, $dataset) {
        $upLimit = $command->GetUpLimit();
        $limitCount = $command->GetLimitCount();
        if (isset($upLimit) && isset($limitCount)) {
            $dataset->Seek($upLimit);
            $dataset->SetRowLimit($limitCount);
        }
    }

    protected function DoExecuteSelectCommand($connection, $command) {
        $result = parent::DoExecuteSelectCommand($connection, $command);
        $this->ApplyCommandLimitsToDataset($command, $result);
        return $result;
    }

    public function DoExecuteCustomSelectCommand($connection, $command) {
        $result = parent::DoExecuteCustomSelectCommand($connection, $command);
        $this->ApplyCommandLimitsToDataset($command, $result);
        return $result;
    }

    public function QuoteIdentifier($identifier) {
        return '[' . $identifier . ']';
    }

    private function EnableIdentityInserts($connection, $tableName, $enabled) {
        $connection->ExecSQL(
            sprintf('SET IDENTITY_INSERT %s %s',
                $this->QuoteTableIdentifier($tableName),
                $enabled ? 'ON' : 'OFF'
            )
        );
    }

    public function ExecuteInsertCommand($connection, $command) {
        if ($command->GetAutoincrementInsertion())
            $this->EnableIdentityInserts($connection, $command->GetTableName(), true);

        parent::ExecuteInsertCommand($connection, $command);

        if ($command->GetAutoincrementInsertion())
            $this->EnableIdentityInserts($connection, $command->GetTableName(), false);
    }

    public static function RetrieveServerVersion(EngConnection $connection) {
        try {
            $values = array();
            $connection->ExecQueryToArray('EXEC master..xp_msver \'ProductVersion\'', $values);
            if (count($values) > 0) {
                $strVersion = $values[0]['Character_Value'];
                list($major, $minor) = explode('.', $strVersion);
                $connection->GetServerVersion()->SetMajor($major);
                $connection->GetServerVersion()->SetMinor($minor);
            }
        } catch (Exception $e) {
        }
    }
}

class MsCOMConnection extends EngConnection {
    private $lastError = '';
    private $COMConnection;

    protected function DoConnect() {
        $result = true;
        if ($this->HasConnectionParam('windows_auth') && $this->ConnectionParam('windows_auth') == true) {
            $connectionString =
                sprintf('PROVIDER=%s;SERVER=%s;Integrated Security=SSPI;DATABASE=%s',
                    'SQLOLEDB',
                    $this->ConnectionParam('server'),
                    $this->ConnectionParam('database')
                );
        } else {
            $connectionString =
                sprintf('PROVIDER=%s;SERVER=%s;UID=%s;PWD=%s;DATABASE=%s',
                    'SQLOLEDB',
                    $this->ConnectionParam('server'),
                    $this->ConnectionParam('username'),
                    $this->ConnectionParam('password'),
                    $this->ConnectionParam('database')
                );
        }

        $this->COMConnection = new COM('ADODB.Connection');
        try {
            $this->COMConnection->Open($connectionString);
            $this->RetrieveServerVersion();
        } catch (com_exception $e) {
            $this->lastError = $e->getMessage();
            $result = false;
        }
        return $result;
    }

    public function IsDriverSupported() {
        return true;
    }

    protected function DoGetDBMSName() {
        return 'SQL Server';
    }

    protected function DoGetDriverExtensionName() {
        return 'COM';
    }

    public function GetDriverNotSupportedMessage() {
        return sprintf(
            'We were unable to use the %s database because the %s extension for PHP is not available. ' .
                'This extension available on Windows machine only. ',
            $this->DoGetDBMSName(),
            $this->DoGetDriverExtensionName()
        );
    }

    public function RetrieveServerVersion() {
        MsSQLCommandImp::RetrieveServerVersion($this);
    }

    protected function DoDisconnect() {
        $this->COMConnection->Close();
    }

    protected function DoCreateDataReader($sql) {
        return new MsCOMDataReader($this, $sql);
    }

    public function GetConnectionHandle() {
        return $this->COMConnection;
    }

    protected function DoExecSQL($sql) {
        try {
            $this->COMConnection->Execute($sql);
        } catch (com_exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
        return true;

    }

    public function SupportsLastInsertId() {
        return true;
    }

    public function GetLastInsertId() {
        return $this->ExecScalarSQL('SELECT @@identity;');
    }

    protected function doExecScalarSQL($sql) {
        try {
            $result = $this->COMConnection->Execute($sql);
        }
        catch (com_exception $e) {
            return false;
        }
        return $result->Fields[0]->Value;
    }

    public function executeQuery($sql) {
        try {
            $result = $this->GetConnectionHandle()->Execute($sql);
        } catch (com_exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
        return $result;
    }

    public function DoLastError() {
        return $this->lastError;
    }
}

class MsCOMDataReader extends EngDataReader {
    private $queryResult;
    private $lastFetchedRow;
    private $rowsFetched;

    /** @var MsCOMConnection */
    private $comConnection;

    public function __construct($connection, $sql) {
        parent::__construct($connection, $sql);
        $this->queryResult = null;
        $this->comConnection = $connection;
    }

    protected function FetchField() {
        RaiseNotSupportedException();
    }

    protected function FetchFields() {
        for ($i = 0; $i < $this->queryResult->Fields->Count; $i++)
            $this->AddField($this->queryResult->Fields[$i]->Name);
    }

    protected function DoOpen() {
        $this->rowsFetched = 1;
        $this->queryResult = $this->comConnection->executeQuery($this->GetSQL());
        if (!$this->queryResult)
            return false;
        return true;
    }

    public function Opened() {
        return $this->queryResult ? true : false;
    }

    public function Seek($ARowIndex) {
        if (!($this->queryResult->EOF() && $this->queryResult->BOF()))
            $this->queryResult->Move($ARowIndex);
    }

    private function IsBlobField($field) {
        return $field->Type == 205 && $field->Type == 205;
    }

    private function ExtractBlobValue($field) {
        $result = '';
        if ($field->ActualSize) {
            $chunk = $field->GetChunk($field->ActualSize);
            $result = str_pad("", count($chunk));
            $j = 0;
            foreach ($chunk as $byte)
                $result[$j++] = chr($byte);
        }
        return $result;
    }

    public function Next() {
        if ($this->GetRowLimit() != -1 && $this->rowsFetched > $this->GetRowLimit())
            return false;
        if ($this->queryResult->EOF())
            return false;
        for ($i = 0; $i < $this->queryResult->Fields->Count; $i++) {
            $currentField = $this->queryResult->Fields[$i];
            if (!isset($currentField->Value))
                $this->lastFetchedRow[$currentField->Name] = null;
            elseif ($this->IsBlobField($currentField))
                $this->lastFetchedRow[$currentField->Name] = $this->ExtractBlobValue($currentField); else
                $this->lastFetchedRow[$currentField->Name] = $currentField->Value;
        }
        $this->queryResult->MoveNext();
        $this->rowsFetched++;
        return true;
    }

    protected function GetDateTimeFieldValueByName(&$value) {
        if (isset($value))
            if (is_object($value) && get_class($value) == 'variant' && version_compare(PHP_VERSION, '5.1', '<')) {
                //$offset =  (60 * 60 * 24) + strtotime('1970-01-02 00:00:00');
                return new SMDateTime(variant_date_to_timestamp($value));
            }
            else
                return SMDateTime::Parse(strval($value), '%Y-%m-%d %H:%M:%S');
        else
            return null;
    }

    public function GetFieldValueByName($AFieldName) {
        return $this->GetActualFieldValue($AFieldName, $this->lastFetchedRow[$AFieldName]);
    }
}

class MsConnection extends EngConnection {
    private $connectionHandle;

    protected function DoConnect() {
        $result = false;
        $this->connectionHandle = @mssql_connect(
            $this->ConnectionParam('server'),
            $this->ConnectionParam('username'),
            $this->ConnectionParam('password'));

        if ($this->connectionHandle)
            if (@mssql_select_db($this->ConnectionParam('database'), $this->connectionHandle)) {
                $result = true;
                $this->RetrieveServerVersion();
            }
        return $result;
    }

    public function RetrieveServerVersion() {
        MsSQLCommandImp::RetrieveServerVersion($this);
    }

    protected function DoCreateDataReader($sql) {
        return new MsDataReader($this, $sql);
    }

    public function IsDriverSupported() {
        return function_exists('mssql_connect');
    }

    protected function DoGetDBMSName() {
        return 'SQL server';
    }

    protected function DoGetDriverExtensionName() {
        return 'mssql';
    }

    protected function DoGetDriverInstallationLink() {
        return 'http://www.php.net/manual/en/mssql.installation.php';
    }

    protected function DoDisconnect() {
        mssql_close($this->connectionHandle);
    }

    public function GetConnectionHandle() {
        return $this->connectionHandle;
    }

    public function SupportsLastInsertId() {
        return true;
    }

    public function GetLastInsertId() {
        return $this->ExecScalarSQL('SELECT @@identity;');
    }

    public function ExecSQL($sql) {
        return mssql_query($sql, $this->GetConnectionHandle()) ? true : false;
    }

    public function ExecScalarSQL($sql) {
        $queryHandle = mssql_query($sql, $this->GetConnectionHandle());
        $queryResult = mssql_fetch_array($queryHandle, MYSQL_NUM);
        return $queryResult[0];
    }

    public function DoLastError() {
        return mssql_get_last_message();
    }
}

class MsDataReader extends EngDataReader {
    private $queryResult;
    private $lastFetchedRow;
    private $rowsFetched;

    protected function FetchField() {
        RaiseNotSupportedException();
    }

    protected function FetchFields() {
        for ($i = 0; $i < mssql_num_fields($this->queryResult); $i++)
            $this->AddField(mssql_field_name($this->queryResult, $i));
    }

    protected function DoOpen() {
        $this->queryResult = mssql_query($this->GetSQL(), $this->GetConnection()->GetConnectionHandle());
        $this->rowsFetched = 0;
        return $this->queryResult;
    }

    public function __construct($connection, $sql) {
        parent::__construct($connection, $sql);
        $this->queryResult = null;
    }

    public function Opened() {
        return $this->queryResult ? true : false;
    }

    public function Seek($rowIndex) {
        mssql_data_seek($this->queryResult, $rowIndex);
    }

    public function Next() {
        if ($this->GetRowLimit() != -1 && $this->rowsFetched >= $this->GetRowLimit())
            return false;
        $this->lastFetchedRow = mssql_fetch_array($this->queryResult, MSSQL_ASSOC);
        $this->rowsFetched++;
        return $this->lastFetchedRow ? true : false;
    }

    public function GetFieldValueByName($fieldName) {
        return $this->GetActualFieldValue($fieldName, $this->lastFetchedRow[$fieldName]);
    }
}

class SqlSrvConnection extends EngConnection {
    private $connectionHandle;

    protected function DoConnect() {
        $result = false;

        $serverName = $this->ConnectionParam('server');

        if ($this->HasConnectionParam('windows_auth') && $this->ConnectionParam('windows_auth') == true) {
            $connectionInfo = array(
                'Database' => $this->ConnectionParam('database'),
                'APP' => 'MsSQL PHP Generator'
            );
        } else {
            $connectionInfo = array(
                'Database' => $this->ConnectionParam('database'),
                'UID' => $this->ConnectionParam('username'),
                'PWD' => $this->ConnectionParam('password'),
                'APP' => 'MsSQL PHP Generator'
            );
        }
        $this->connectionHandle = sqlsrv_connect($serverName, $connectionInfo);

        if ($this->connectionHandle) {
            $result = true;
            $this->RetrieveServerVersion();
        }
        return $result;
    }

    public function IsDriverSupported() {
        return function_exists('sqlsrv_connect');
    }

    protected function DoGetDBMSName() {
        return 'SQL Server';
    }

    protected function DoGetDriverExtensionName() {
        return 'sqlsrv';
    }

    protected function DoGetDriverInstallationLink() {
        return 'http://www.php.net/manual/en/sqlsrv.installation.php';
    }

    public function RetrieveServerVersion() {
        MsSQLCommandImp::RetrieveServerVersion($this);
    }

    protected function DoDisconnect() {
        sqlsrv_close($this->connectionHandle);
    }

    protected function DoCreateDataReader($sql) {
        return new SqlSrvDataReader($this, $sql);
    }

    public function GetConnectionHandle() {
        return $this->connectionHandle;
    }

    public function SupportsLastInsertId() {
        return true;
    }

    public function GetLastInsertId() {
        return $this->ExecScalarSQL('SELECT @@identity;');
    }

    protected function DoExecSQL($sql) {
        return sqlsrv_query($this->GetConnectionHandle(), $sql) ? true : false;
    }

    protected function doExecScalarSQL($sql) {
        if ($queryHandle = sqlsrv_query($this->GetConnectionHandle(), $sql)) {
            $queryResult = sqlsrv_fetch_array($queryHandle);
            return $queryResult[0];
        }
        return false;
    }

    protected function doExecQueryToArray($sql, &$array) {
        if ($queryHandle = sqlsrv_query($this->GetConnectionHandle(), $sql)) {
            while ($row = sqlsrv_fetch_array($queryHandle, SQLSRV_FETCH_BOTH)) {
                $array[] = $row;
            }
            return true;
        }
        return false;
    }

    public function DoLastError() {
        $result = '';
        $errors = sqlsrv_errors();
        if ($errors != null)
            foreach ($errors as $error)
                AddStr($result, $error['message'], '<br/>');
        return $result;
    }
}

class SqlSrvDataReader extends EngDataReader {
    private $queryResult;
    private $lastFetchedRow;
    private $rowsFetched;

    /** @var SqlSrvConnection */
    private $sqlSrvConnection;

    public function __construct(SqlSrvConnection $connection, $sql) {
        parent::__construct($connection, $sql);
        $this->queryResult = null;
        $this->sqlSrvConnection = $connection;
    }

    protected function FetchField() {
        RaiseNotSupportedException();
    }

    protected function FetchFields() {
        foreach (sqlsrv_field_metadata($this->queryResult) as $fieldMetadata)
            $this->AddField($fieldMetadata['Name']);
    }

    protected function DoOpen() {
        $this->queryResult = sqlsrv_query($this->sqlSrvConnection->GetConnectionHandle(), $this->GetSQL());
        $this->rowsFetched = 0;
        return $this->queryResult;
    }

    public function Opened() {
        return $this->queryResult ? true : false;
    }

    public function Seek($rowIndex) {
        $recordCount = 0;
        while ($recordCount < $rowIndex) {
            if (!sqlsrv_fetch_array($this->queryResult))
                return;
            $recordCount++;
        }
    }

    public function Next() {
        if ($this->GetRowLimit() != -1 && $this->rowsFetched >= $this->GetRowLimit())
            return false;
        $this->lastFetchedRow = sqlsrv_fetch_array($this->queryResult);
        $this->rowsFetched++;
        return $this->lastFetchedRow ? true : false;
    }

    public function GetFieldValueByName($fieldName) {
        return $this->GetActualFieldValue($fieldName, $this->lastFetchedRow[$fieldName]);
    }
}

