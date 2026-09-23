<?php
namespace Combodo\iTop\Extension\ITRemote\Hooks;

use Exception;
use iPopupMenuExtension;
use MetaModel;
use Dict;
use URLPopupMenuItem;
use DBObject;
use DBObjectSearch;
use DBObjectSet;
use IssueLog;

class ConnectToDeviceViaITRemoteMenuExtension implements iPopupMenuExtension
{
    const MODULE_CODE = 'itop-to-itremote';

    /**
     * @param int   $iMenuId
     * @param mixed $param
     *
     * @return array|object[]
     * @throws \Exception
     */
    public static function EnumItems($iMenuId, $param)
    {
        switch($iMenuId)
        {
            case iPopupMenuExtension::MENU_OBJDETAILS_ACTIONS:
                // In this case $param is a DBObject
                $aResult = array();
                $oObj = $param;

                // Applies only on allowed classes
                $aAllowedClasses = MetaModel::GetConfig()->GetModuleSetting(static::MODULE_CODE, 'allowed_classes', array());
                $sURLTemplate = MetaModel::GetConfig()->GetModuleSetting(static::MODULE_CODE, 'url', 'https://control.itremote.com/Remote/OpenAccess?deviceName=%1$s');
                foreach($aAllowedClasses as $sClass => $aClassConfig)
                {
                    // Check if the current object is a child of the allowed class and if it is in scope
                    if (is_a($oObj, $sClass, true) && (static::IsCurrentObjectInScope($aClassConfig, $oObj, $sClass))) {
                        $sMenuCode = 'Menu:ConnectToITRemote';
                        $oMenuItem = new URLPopupMenuItem(
                            $sMenuCode,
                            Dict::S($sMenuCode),
                            sprintf($sURLTemplate, urlencode($oObj->GetName())),
                            '_blank',
                        );
                        $oMenuItem->SetTooltip(Dict::S($sMenuCode . '+'));
                        $oMenuItem->SetIconClass('fas fa-desktop');

                        $aResult = [ $oMenuItem ];

                        // Stop on the first matching class
                        break;
                    }
                }
                break;

            default:
                // Unknown type of menu, do nothing
                $aResult = array();
                break;
        }
        return $aResult;
    }

    /**
     * Check if current object is in scope of the module configuration. If no scope is defined, consider the object in scope.
     *
     * @param array $aClassConfig configuration for an allowed class, including the 'scope' key if defined
     * @param DBObject $oObj current object
     * @param string $sClass class of the scope to check against
     * @return bool
     * @throws Exception
     */
    public static function IsCurrentObjectInScope(array $aClassConfig, DBObject $oObj, string $sClass): bool
    {
        try {
            // Check if the current object is in the scope of the module configuration
            if (isset($aClassConfig['scope'])) {
                $sScope = $aClassConfig['scope'];
                // Parsing the query validates its syntax and consistency with the data model.
                $oScope = DBObjectSearch::FromOQL($sScope);
                // Check that the OQL search for a class which is equal to $sClass
                if ($oScope->GetClass() !== $sClass) {
                    throw new Exception("The scope query for class '$sClass' is not valid. The scope query must return objects of that exact class");
                }
                $oScope->AddCondition('id', $oObj->GetKey());
                $oSet = new DBObjectSet($oScope);
                return ($oSet->Count() > 0);
            } else {
                // If no scope is defined, consider the object in scope
                return true;
            }

        } catch (Exception $e) {
            IssueLog::Error('An error occurred when trying to check if current object is eligible to IT Remote connection', 'itop-to-itremote', [
                'obj_class' => $sClass,
                'scope' => isset($aClassConfig['scope']) ? $aClassConfig['scope'] : '',
                'obj_name' => $oObj->GetName(),
            ]);
            return false;
        }
    }
}