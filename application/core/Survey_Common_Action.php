<?php

/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*
*/

/**
* Survey Common Action
*
* This controller contains common functions for survey related views.
*
* @package        LimeSurvey
* @subpackage    Backend
* @author        LimeSurvey Team
* @method        void index()
*/



class Survey_Common_Action extends CAction
{
    public function __construct($controller = null, $id = null)
    {
        parent::__construct($controller, $id);
        Yii::app()->request->updateNavigationStack();
        // Make sure viewHelper can be autoloaded
        Yii::import('application.helpers.viewHelper');
    }

    /**
     * Override runWithParams() implementation in CAction to help us parse
     * requests with subactions.
     *
     * @param array $params URL Parameters
     * @return bool
     */
    public function runWithParams($params)
    {
        // Default method that would be called if the subaction and run() do not exist
        $sDefault = 'index';
        // Check for a subaction
        if (empty($params['sa'])) {
            $sSubAction = $sDefault; // default
        } else {
            $sSubAction = $params['sa'];
        }
        // Check if the class has the method
        $oClass = new ReflectionClass($this);
        if (!$oClass->hasMethod($sSubAction)) {
            // If it doesn't, revert to default Yii method, that is run() which should reroute us somewhere else
            $sSubAction = 'run';
        }

        // Populate the params. eg. surveyid -> iSurveyId
        $params = $this->_addPseudoParams($params);

        if (!empty($params['iSurveyId'])) {
            LimeExpressionManager::SetSurveyId($params['iSurveyId']); // must be called early - it clears internal cache if a new survey is being used
        }
        // Check if the method is public and of the action class, not its parents
        // ReflectionClass gets us the methods of the class and parent class
        // If the above method existence check passed, it might not be neceessary that it is of the action class
        $oMethod  = new ReflectionMethod($this, $sSubAction);

        // Get the action classes from the admin controller as the urls necessarily do not equal the class names. Eg. survey -> surveyaction
        // Merges it with actions from admin modules
        $aActions = array_merge(Yii::app()->getController()->getActionClasses(), Yii::app()->getController()->getAdminModulesActionClasses() );

        if (empty($aActions[$this->getId()]) || strtolower($oMethod->getDeclaringClass()->name) != strtolower($aActions[$this->getId()]) || !$oMethod->isPublic()) {
            // Either action doesn't exist in our whitelist, or the method class doesn't equal the action class or the method isn't public
            // So let us get the last possible default method, ie. index
            $oMethod = new ReflectionMethod($this, $sDefault);
        }

        // We're all good to go, let's execute it
        // runWithParamsInternal would automatically get the parameters of the method and populate them as required with the params
        return parent::runWithParamsInternal($this, $oMethod, $params);
    }

    /**
     * Some functions have different parameters, which are just an alias of the
     * usual parameters we're getting in the url. This function just populates
     * those variables so that we don't end up in an error.
     *
     * This is also used while rendering wrapped template
     * {@link Survey_Common_Action::_renderWrappedTemplate()}
     *
     * @param array $params Parameters to parse and populate
     * @return array Populated parameters
     * @throws CHttpException
     */
    private function _addPseudoParams($params)
    {
        // Return if params isn't an array
        if (empty($params) || !is_array($params)) {
            return $params;
        }

        $pseudos = array(
            'id' => 'iId',
            'gid' => 'iGroupId',
            'qid' => 'iQuestionId',
            /* priority is surveyid,surveyId,sid : surveyId=1&sid=2 set iSurveyId to 1 */
            'sid' => array('iSurveyId', 'iSurveyID', 'surveyid'), // Old link use sid
            'surveyId' => array('iSurveyId', 'iSurveyID', 'surveyid'), // PluginHelper->sidebody : if disable surveyId usage : broke API
            'surveyid' => array('iSurveyId', 'iSurveyID', 'surveyid'),
            'srid' => 'iSurveyResponseId',
            'scid' => 'iSavedControlId',
            'uid' => 'iUserId',
            'ugid' => 'iUserGroupId',
            'fieldname' => 'sFieldName',
            'fieldtext' => 'sFieldText',
            'action' => 'sAction',
            'lang' => 'sLanguage',
            'browselang' => 'sBrowseLang',
            'tokenids' => 'aTokenIds',
            'tokenid' => 'iTokenId',
            'subaction' => 'sSubAction', // /!\ Already filled by sa : can be different (usage of subaction in quota at 2019-09-04)
        );
        // Foreach pseudo, take the key, if it exists,
        // Populate the values (taken as an array) as keys in params
        // with that key's value in the params
        // Chek is 2 params are equal for security issue.
        foreach ($pseudos as $key => $pseudo) {
            // We care only for user parameters, not by code parameters (see issue #15221)
            if ($checkParam = Yii::app()->getRequest()->getParam($key)) {
                $pseudo = (array) $pseudo;
                foreach ($pseudo as $pseud) {
                    if (empty($params[$pseud])) {
                        $params[$pseud] = $checkParam;
                    } elseif($params[$pseud] != $checkParam){
                        // Throw error about multiple params (and if they are different) #15204
                        throw new CHttpException(403, sprintf(gT("Invalid parameter %s (%s already set)"),$pseud,$key));
                    }
                }
            }
        }

        /* Control sid,gid and qid params validity see #12434 */
        // Fill param with according existing param, replace existing parameters.
        // iGroupId/gid can be found with qid/iQuestionId
        if (!empty($params['iQuestionId'])) {
            if ((string) (int) $params['iQuestionId'] !== (string) $params['iQuestionId']) {
                // pgsql need filtering before find
                throw new CHttpException(403, gT("Invalid question id"));
            }
            $oQuestion = Question::model()->find("qid=:qid", array(":qid"=>$params['iQuestionId'])); //Move this in model to use cache
            if (!$oQuestion) {
                throw new CHttpException(404, gT("Question not found"));
            }
            if (!isset($params['iGroupId'])) {
                $params['iGroupId'] = $params['gid'] = $oQuestion->gid;
            }
        }
        // iSurveyId/iSurveyID/sid can be found with gid/iGroupId
        if (!empty($params['iGroupId'])) {
            if ((string) (int) $params['iGroupId'] !== (string) $params['iGroupId']) {
                // pgsql need filtering before find
                throw new CHttpException(403, gT("Invalid group id"));
            }
            $oGroup = QuestionGroup::model()->find("gid=:gid", array(":gid"=>$params['iGroupId'])); //Move this in model to use cache
            if (!$oGroup) {
                throw new CHttpException(404, gT("Group not found"));
            }
            if (!isset($params['iSurveyId'])) {
                $params['iSurveyId'] = $params['iSurveyID'] = $params['surveyid'] = $params['sid'] = $oGroup->sid;
            }
        }
        // Finally control validity of sid
        if (!empty($params['iSurveyId'])) {
            if ((string) (int) $params['iSurveyId'] !== (string) $params['iSurveyId']) {
                // pgsql need filtering before find
                // 403 mean The request was valid, but the server is refusing action.
                throw new CHttpException(403, gT("Invalid survey id"));
            }
            $oSurvey = Survey::model()->findByPk($params['iSurveyId']);
            if (!$oSurvey) {
                throw new CHttpException(404, gT("Survey not found"));
            }
            // Minimal permission needed, extra permission must be tested in each controller
            if (!Permission::model()->hasSurveyPermission($params['iSurveyId'], 'survey', 'read')) {
                // 403 mean (too) The user might not have the necessary permissions for a resource.
                // 401 semantically means "unauthenticated"
                throw new CHttpException(403);
            }
            $params['iSurveyId'] = $params['iSurveyID'] = $params['surveyid'] = $params['sid'] = $oSurvey->sid;
        }
        // Finally return the populated array
        return $params;
    }

    /**
     * Action classes require them to have a run method. We reroute it to index
     * if called.
     */
    public function run()
    {
        $this->index();
    }

    /**
     * Routes the action into correct subaction
     *
     * @access protected
     * @param string $sa
     * @param string[] $get_vars
     * @return mixed
     */
    protected function route($sa, array $get_vars)
    {
        $func_args = array();
        foreach ($get_vars as $k => $var) {
                    $func_args[$k] = Yii::app()->request->getQuery($var);
        }

        return call_user_func_array(array($this, $sa), $func_args);
    }

    /**
     * @inheritdoc
     * @param string $_viewFile_
     */
    public function renderInternal($_viewFile_, $_data_ = null, $_return_ = false)
    {
        // we use special variable names here to avoid conflict when extracting data
        if (is_array($_data_)) {
            extract($_data_, EXTR_PREFIX_SAME, 'data');
        } else {
            $data = $_data_;
        }

        if ($_return_) {
            ob_start();
            ob_implicit_flush(0);
            require($_viewFile_);
            return ob_get_clean();
        } else {
            require($_viewFile_);
        }
    }

    /**
     * Rendering the subviews and views of _renderWrappedTemplate
     *
     * @param string $sAction
     * @param array|string $aViewUrls
     * @param array $aData
     * @return string
     */
    protected function renderCentralContents($sAction, $aViewUrls, $aData = [])
    {

        //// This will be handle by subviews inclusions
        $aViewUrls = (array) $aViewUrls; $sViewPath = '/admin/';
        if (!empty($sAction)) {
                    $sViewPath .= $sAction.'/';
        }
        ////  TODO : while refactoring, we must replace the use of $aViewUrls by $aData[.. conditions ..], and then call to function such as $this->_nsurveysummary($aData);
        // Load views
        $content = "";

        foreach ($aViewUrls as $sViewKey => $viewUrl) {
            if (empty($sViewKey) || !in_array($sViewKey, array('message', 'output'))) {
                if (is_numeric($sViewKey)) {
                    $content .= Yii::app()->getController()->renderPartial($sViewPath.$viewUrl, $aData, true);
                } elseif (is_array($viewUrl)) {
                    foreach ($viewUrl as $aSubData) {
                        $aSubData = array_merge($aData, $aSubData);
                        $content .= Yii::app()->getController()->renderPartial($sViewPath.$sViewKey, $aSubData, true);
                    }
                }
            } else {
                switch ($sViewKey) {
                    //// We'll use some Bootstrap alerts, and call them inside each correct view.
                    // Message
                    case 'message' :
                        if (empty($viewUrl['class'])) {
                            $content .= Yii::app()->getController()->_showMessageBox($viewUrl['title'], $viewUrl['message'], null, true);
                        } else {
                            $content .= Yii::app()->getController()->_showMessageBox($viewUrl['title'], $viewUrl['message'], $viewUrl['class'], true);
                        }
                        break;

                        // Output
                    case 'output' :
                        //// TODO : http://goo.gl/ABl5t5
                        $content .= $viewUrl;

                        if (isset($aViewUrls['afteroutput'])) {
                            $content .= $aViewUrls['afteroutput'];
                        }
                        break;
                }
            }
        }
        return $content;
    }

    /**
     * Renders template(s) wrapped in header and footer
     *
     * Addition of parameters should be avoided if they can be added to $aData
     *
     * NOTE FROM LOUIS : We want to remove this function, wich doesn't respect MVC pattern.
     * The work it's doing should be handle by layout files, and subviews inside views.
     * Eg : for route "admin/survey/sa/listquestiongroups/surveyid/282267"
     *       the Group controller should use a main layout (with admin menu bar as a widget), then render the list view, in wich the question group bar is called as a subview.
     *
     * So for now, we try to evacuate all the renderWrappedTemplate logic (if statements, etc.) to subfunctions, then it will be easier to remove.
     * Comments starting with //// indicate how it should work in the future
     *
     * @param string $sAction Current action, the folder to fetch views from
     * @param array|string $aViewUrls View url(s)
     * @param array $aData Data to be passed on. Optional.
     * @param string|boolean $sRenderFile File to be rendered as a layout. Optional.
     */
    protected function _renderWrappedTemplate($sAction = '', $aViewUrls = array(), $aData = array(), $sRenderFile = false)
    {
        // Gather the data
        $aData = $this->_addPseudoParams($aData); // This call 2 times _addPseudoParams because it's already done in runWithParams : why ?

        $basePath = (string) Yii::getPathOfAlias('application.views.admin.super');

        if ($sRenderFile == false) {
            if (!empty($aData['surveyid'])) {

                $aData['oSurvey'] = Survey::model()->findByPk($aData['surveyid']);

                // Needed to evaluate EM expressions in question summary
                // See bug #11845
                LimeExpressionManager::SetSurveyId($aData['surveyid']);
                LimeExpressionManager::StartProcessingPage(false,true);

                $renderFile = $basePath.'/layout_insurvey.php';
            } else {
                $renderFile = $basePath.'/layout_main.php';
            }
        } else {
            $renderFile = $basePath.'/'.$sRenderFile;
        }
        $content = $this->renderCentralContents($sAction, $aViewUrls, $aData);
        $out = $this->renderInternal($renderFile, ['content' => $content, 'aData' => $aData], true);

        App()->getClientScript()->render($out);
        echo $out;
    }

    /**
     * Display the update notification
     */
    protected function _updatenotification()
    {
        // Never use Notification model for database update.
        // TODO: Real fix: No database queries while doing database update, meaning
        // don't call _renderWrappedTemplate.
        if (get_class($this) == 'databaseupdate') {
            return;
        }

        if (!Yii::app()->user->isGuest && Yii::app()->getConfig('updatable')) {
            $updateModel = new UpdateForm();
            $updateNotification = $updateModel->updateNotification;

            if ($updateNotification->result) {
                return $this->getController()->renderPartial("/admin/update/_update_notification", array('security_update_available'=>$updateNotification->security_update));
            }
        }
    }

    /**
     * Display notifications
     */
    private function _notifications()
    {
            $aMessage = App()->session['arrayNotificationMessages'];
            if (!is_array($aMessage)) {
                $aMessage = array();
            }
            unset(App()->session['arrayNotificationMessages']);
            return $this->getController()->renderPartial("notifications/notifications", array('aMessage'=>$aMessage));
    }

    /**
     * Survey summary
     * @param array $aData
     */
    private function _nsurveysummary($aData)
    {
        if (isset($aData['display']['surveysummary'])) {
            if ((empty($aData['display']['menu_bars']['surveysummary']) || !is_string($aData['display']['menu_bars']['surveysummary'])) && !empty($aData['gid'])) {
                $aData['display']['menu_bars']['surveysummary'] = 'viewgroup';
            }
            $this->_surveysummary($aData);
        }
    }

    /**
     * Header
     * @param array $aData
     */
    private function _showHeaders($aData, $sendHTTPHeader = true)
    {
        if (!isset($aData['display']['header']) || $aData['display']['header'] !== false) {
            // Send HTTP header
            if ($sendHTTPHeader) {
                header("Content-type: text/html; charset=UTF-8"); // needed for correct UTF-8 encoding
            }
            Yii::app()->getController()->_getAdminHeader();
        }
    }

    /**
     * _showadminmenu() function returns html text for the administration button bar
     *
     * @access public
     * @param $aData
     * @return string
     * @global string $homedir
     * @global string $scriptname
     * @global string $surveyid
     * @global string $setfont
     * @global string $imageurl
     * @global int $surveyid
     */
    public function _showadminmenu($aData)
    {
        // We don't wont the admin menu to be shown in login page
        if (!Yii::app()->user->isGuest) {
            // Default password notification
            if (Yii::app()->session['pw_notify'] && Yii::app()->getConfig("debug") < 2) {
                $not = new UniqueNotification(array(
                    'user_id' => App()->user->id,
                    'importance' => Notification::HIGH_IMPORTANCE,
                    'title' => gT('Password warning'),
                    'message' => '<span class="fa fa-exclamation-circle text-warning"></span>&nbsp;'.
                        gT("Warning: You are still using the default password ('password'). Please change your password and re-login again.")
                ));
                $not->save();
            }
            if (!(App()->getConfig('ssl_disable_alert')) && strtolower(App()->getConfig('force_ssl') != 'on') && \Permission::model()->hasGlobalPermission("superadmin")) {
                $not = new UniqueNotification(array(
                    'user_id' => App()->user->id,
                    'importance' => Notification::HIGH_IMPORTANCE,
                    'title' => gT('SSL not enforced'),
                    'message' => '<span class="fa fa-exclamation-circle text-warning"></span>&nbsp;'.
                        gT("Warning: Please enforce SSL encrpytion in Global settings/Security after SSL is properly configured for your webserver.")
                ));
                $not->save();                
            }

            // Count active survey
            $aData['dataForConfigMenu']['activesurveyscount'] = $aData['activesurveyscount'] = Survey::model()->permission(Yii::app()->user->getId())->active()->count();

            // Count survey
            $aData['dataForConfigMenu']['surveyscount'] = Survey::model()->count();

            // Count user
            $aData['dataForConfigMenu']['userscount'] = User::model()->count();

            //Check if have a comfortUpdate key
            if (getGlobalSetting('emailsmtpdebug') != '') {
                $aData['dataForConfigMenu']['comfortUpdateKey'] = gT('Activated');
            } else {
                $aData['dataForConfigMenu']['comfortUpdateKey'] = gT('None');
            }

            $aData['sitename'] = Yii::app()->getConfig("sitename");

            $updateModel = new UpdateForm();
            $updateNotification = $updateModel->updateNotification;
            $aData['showupdate'] = Yii::app()->getConfig('updatable') && $updateNotification->result && !$updateNotification->unstable_update;

            // Fetch extra menus from plugins, e.g. last visited surveys
            $aData['extraMenus'] = $this->fetchExtraMenus($aData);

            // Get notification menu
            $surveyId = isset($aData['surveyid']) ? $aData['surveyid'] : null;
            Yii::import('application.controllers.admin.NotificationController');
            $aData['adminNotifications'] = NotificationController::getMenuWidget($surveyId, true /* show spinner */);

            $this->getController()->renderPartial("/admin/super/adminmenu", $aData);
        }
        return null;
    }

    private function _titlebar($aData)
    {
        if (isset($aData['title_bar'])) {
            $this->getController()->renderPartial("/admin/super/title_bar", $aData);
        }
    }

    private function _tokenbar($aData)
    {
        if (isset($aData['token_bar'])) {

            if (isset($aData['token_bar']['closebutton']['url'])) {
                $sAlternativeUrl = $aData['token_bar']['closebutton']['url'];
                $aData['token_bar']['closebutton']['url'] = Yii::app()->request->getUrlReferrer(Yii::app()->createUrl($sAlternativeUrl));
            }

            $this->getController()->renderPartial("/admin/token/token_bar", $aData);
        }
    }

    /**
     * Render the save/cancel bar for Organize question groups/questions
     *
     * @param array $aData
     *
     * @since 2014-09-30
     * @author Olle Haerstedt
     */
    private function _organizequestionbar($aData)
    {
        if (isset($aData['organizebar'])) {
            if (isset($aData['questionbar']['closebutton']['url'])) {
                $sAlternativeUrl = $aData['questionbar']['closebutton']['url'];
                $aData['questionbar']['closebutton']['url'] = Yii::app()->request->getUrlReferrer(Yii::app()->createUrl($sAlternativeUrl));
            }

            $aData['questionbar'] = $aData['organizebar'];
            $this->getController()->renderPartial("/admin/survey/Question/questionbar_view", $aData);
        }
    }

    public function _generaltopbar($aData) {
        $aData['topBar'] = isset($aData['topBar']) ? $aData['topBar'] : [];
        $aData['topBar'] = array_merge(
            [
                'type' => 'survey',
                'sid' => $aData['sid'],
                'gid' => $aData['gid'] ?? 0,
                'qid' => $aData['qid'] ?? 0,
                'showSaveButton' => false
            ],
            $aData['topBar']
        );
        
        Yii::app()->getClientScript()->registerPackage('admintoppanel');     
        $this->getController()->renderPartial("/admin/survey/topbar/topbar_view", $aData);
    }
    public function _generaltopbarAdditions($aData) {
        $aData['topBar'] = isset($aData['topBar']) ? $aData['topBar'] : [];
        $aData['topBar'] = array_merge(
            [
                'type' => 'survey',
                'sid' => $aData['sid'],
                'gid' => $aData['gid'] ?? 0,
                'qid' => $aData['qid'] ?? 0,
                'showSaveButton' => false
            ],
            $aData['topBar']
        );
           
        Yii::app()->getClientScript()->registerPackage((getLanguageRTL(Yii::app()->language) ? 'admintoppanelrtl' : 'admintoppanelltr'));
        
        if (isset($aData['qid'])) {
            $aData['topBar']['type'] = isset($aData['topBar']['type']) ? $aData['topBar']['type'] : 'question';
        } else if (isset($aData['gid'])) {
            $aData['topBar']['type'] = isset($aData['topBar']['type']) ? $aData['topBar']['type'] : 'group';
        } else if (isset($aData['surveyid'])) {
            $sid = $aData['sid'];
            $oSurvey       = Survey::model()->findByPk($sid);
            $respstatsread = Permission::model()->hasSurveyPermission($sid, 'responses', 'read')  ||
                            Permission::model()->hasSurveyPermission($sid, 'statistics', 'read') ||
                            Permission::model()->hasSurveyPermission($sid, 'responses', 'export');
            $surveyexport = Permission::model()->hasSurveyPermission($sid, 'surveycontent', 'export');
            $oneLanguage  = (count($oSurvey->allLanguages) == 1);
            $aData['respstatsread'] = $respstatsread;
            $aData['surveyexport']  = $surveyexport;
            $aData['onelanguage']   = $oneLanguage;
            $aData['topBar']['type'] = isset($aData['topBar']['type']) ? $aData['topBar']['type'] : 'survey';
        }
        $this->getController()->renderPartial("/admin/survey/topbar/topbar_additions", $aData);

    }

    /**
     * Shows admin menu for question
     *
     * @param array $aData
     */
    public function _questionbar($aData)
    {
        if (isset($aData['questionbar'])) {
            if (is_object($aData['oSurvey'])) {

                $iSurveyID = $aData['surveyid'];
                /** @var Survey $oSurvey */
                $oSurvey = $aData['oSurvey'];
                $gid = $aData['gid'];
                $qid = $aData['qid'];

                // action
                $action = (!empty($aData['display']['menu_bars']['qid_action'])) ? $aData['display']['menu_bars']['qid_action'] : null;
                $baselang = $oSurvey->language;

                //Show Question Details
                //Count answer-options for this question
                $aData['qct'] = Answer::model()->countByAttributes(array('qid' => $qid));

                //Count sub-questions for this question
                $aData['sqct'] = Question::model()->countByAttributes(array('parent_qid' => $qid));

                $qrrow = Question::model()->findByAttributes(array('qid' => $qid, 'gid' => $gid, 'sid' => $iSurveyID));
                if (is_null($qrrow)) {
                    return;
                }
                $questionsummary = "";

                // Check if other questions in the Survey are dependent upon this question
                $condarray = getQuestDepsForConditions($iSurveyID, "all", "all", $qid, "by-targqid", "outsidegroup");

                // $surveyinfo = $oSurvey->attributes;
                // $surveyinfo = array_map('flattenText', $surveyinfo);
                $aData['activated'] = $oSurvey->active;

                $qrrow = $qrrow->attributes;
                $aData['languagelist'] = $oSurvey->getAllLanguages();
                $aData['qtypes'] = Question::typeList();
                $aData['action'] = $action;
                $aData['surveyid'] = $iSurveyID;
                $aData['qid'] = $qid;
                $aData['gid'] = $gid;
                $aData['qrrow'] = $qrrow;
                $aData['baselang'] = $baselang;

                $aAttributesWithValues = Question::model()->getAdvancedSettingsWithValues($qid, $qrrow['type'], $iSurveyID, $baselang);

                $DisplayArray = array();
                foreach ($aAttributesWithValues as $aAttribute) {
                    if (($aAttribute['i18n'] == false && isset($aAttribute['value']) && $aAttribute['value'] != $aAttribute['default']) ||
                        ($aAttribute['i18n'] == true && isset($aAttribute['value'][$baselang]) && $aAttribute['value'][$baselang] != $aAttribute['default'])) {
                        if ($aAttribute['inputtype'] == 'singleselect') {
                            if (isset($aAttribute['options'][$aAttribute['value']])) {
                                                            $aAttribute['value'] = $aAttribute['options'][$aAttribute['value']];
                            }
                        }
                        $DisplayArray[] = $aAttribute;
                    }
                }

                $aData['advancedsettings'] = $DisplayArray;
                $aData['condarray'] = $condarray;
                if (isset($aData['questionbar']['closebutton']['url'])) {
                    $sAlternativeUrl = $aData['questionbar']['closebutton']['url'];
                    $aData['questionbar']['closebutton']['url'] = Yii::app()->request->getUrlReferrer(Yii::app()->createUrl($sAlternativeUrl));
                }
                $questionsummary .= $this->getController()->renderPartial('/admin/survey/Question/questionbar_view', $aData, true);
                $this->getController()->renderPartial('/survey_view', ['display'=>$questionsummary]);
            } else {
                Yii::app()->session['flashmessage'] = gT("Invalid survey ID");
                $this->getController()->redirect(array("admin/index"));
            }
        }
    }

    /**
     * Show admin menu for question group view
     *
     * @param array $aData ?
     */
    function _nquestiongroupbar($aData)
    {
        if (isset($aData['questiongroupbar'])) {
            if (!isset($aData['gid'])) {
                if (isset($_GET['gid'])) {
                    $aData['gid'] = $_GET['gid'];
                }
            }

            $aData['surveyIsActive'] = $aData['oSurvey']->active !== 'N';

            $surveyid = $aData['surveyid'];
            $gid = $aData['gid'];
            $oSurvey = $aData['oSurvey'];

            $aData['sumcount4'] = Question::model()->countByAttributes(array('sid' => $surveyid, 'gid' => $gid));

            $sumresult1 = Survey::model()->with(array(
                'languagesettings' => array('condition' => 'surveyls_language=language'))
                )->findByPk($surveyid);
            $aData['activated'] = $activated = $sumresult1->active;
            if($gid !== null) {
                $condarray = getGroupDepsForConditions($surveyid, "all", $gid, "by-targgid");
            }
            $aData['condarray'] = $condarray ?? [];

            $aData['languagelist'] = $oSurvey->getAllLanguages();

            if (isset($aData['questiongroupbar']['closebutton']['url'])) {
                $sAlternativeUrl = $aData['questiongroupbar']['closebutton']['url'];
                $aData['questiongroupbar']['closebutton']['url'] = Yii::app()->request->getUrlReferrer(Yii::app()->createUrl($sAlternativeUrl));
            }

            $this->getController()->renderPartial("/admin/survey/QuestionGroups/questiongroupbar_view", $aData);
        }
    }

    function _fullpagebar($aData)
    {
        if ((isset($aData['fullpagebar']))) {
            if (isset($aData['fullpagebar']['closebutton']['url']) && !isset($aData['fullpagebar']['closebutton']['url_keep'])) {
                $sAlternativeUrl = '/admin/index';
                $aData['fullpagebar']['closebutton']['url'] = Yii::app()->request->getUrlReferrer(Yii::app()->createUrl($sAlternativeUrl));
            }
            $this->getController()->renderPartial("/admin/super/fullpagebar_view", $aData);
        }
    }

    /**
     * Shows admin menu for surveys
     * @param int Survey id
     */
    function _surveybar($aData)
    {
        if ((isset($aData['surveybar']))) {
            $iSurveyID = $aData['surveyid'];
            /** @var Survey $oSurvey */
            $oSurvey = $aData['oSurvey'];
            $gid = isset($aData['gid']) ? $aData['gid'] : null;
            $aData['baselang'] = $oSurvey->language;
            App()->getClientScript()->registerPackage('js-cookie');

            //Parse data to send to view

            // ACTIVATE SURVEY BUTTON

            $condition = array('sid' => $iSurveyID, 'parent_qid' => 0);

            $sumcount3 = Question::model()->countByAttributes($condition); //Checked

            $aData['canactivate'] = $sumcount3 > 0 && Permission::model()->hasSurveyPermission($iSurveyID, 'surveyactivation', 'update');
            $aData['candeactivate'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveyactivation', 'update');
            $aData['expired'] = $oSurvey->expires != '' && ($oSurvey->expires < dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig('timeadjust')));
            $aData['notstarted'] = ($oSurvey->startdate != '') && ($oSurvey->startdate > dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig('timeadjust')));

            // Start of suckerfish menu
            // TEST BUTTON
            if (!$oSurvey->isActive) {
                $aData['icontext'] = gT("Preview survey");
            } else {
                $aData['icontext'] = gT("Execute survey");
            }

            $aData['onelanguage'] = (count($oSurvey->allLanguages) == 1);
            $aData['hasadditionallanguages'] = (count($oSurvey->additionalLanguages) > 0);

            // Survey text elements BUTTON
            $aData['surveylocale'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveylocale', 'read');
            // EDIT SURVEY SETTINGS BUTTON
            $aData['surveysettings'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'read');
            // Survey permission item
            $aData['surveysecurity'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveysecurity', 'read');
            // CHANGE QUESTION GROUP ORDER BUTTON
            $aData['surveycontentread'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'read');
            $aData['groupsum'] = ($oSurvey->groupsCount > 1);
            // SET SURVEY QUOTAS BUTTON
            $aData['quotas'] = Permission::model()->hasSurveyPermission($iSurveyID, 'quotas', 'read');
            // Assessment menu item
            $aData['assessments'] = Permission::model()->hasSurveyPermission($iSurveyID, 'assessments', 'read');
            // Survey text elements BUTTON
            // End if survey properties
            // Tools menu item
            // Delete survey item
            $aData['surveydelete'] = Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'delete');
            // Translate survey item
            $aData['surveytranslate'] = Permission::model()->hasSurveyPermission($iSurveyID, 'translations', 'read');
            // RESET SURVEY LOGIC BUTTON
            //$sumquery6 = "SELECT count(*) FROM ".db_table_name('conditions')." as c, ".db_table_name('questions')."
            // as q WHERE c.qid = q.qid AND q.sid=$iSurveyID"; //Getting a count of conditions for this survey
            // TMSW Condition->Relevance:  How is conditionscount used?  Should Relevance do the same?

            // Only show survey properties menu if at least one item is permitted
            $aData['showSurveyPropertiesMenu'] =
                    $aData['surveylocale']
                || $aData['surveysettings']
                || $aData['surveysecurity']
                || $aData['surveycontentread']
                || $aData['quotas']
                || $aData['assessments'];

            // Put menu items in tools menu
            $event = new PluginEvent('beforeToolsMenuRender', $this);
            $event->set('surveyId', $iSurveyID);
            App()->getPluginManager()->dispatchEvent($event);
            $extraToolsMenuItems = $event->get('menuItems');
            $aData['extraToolsMenuItems'] = $extraToolsMenuItems;

            // Add new menus in survey bar
            $event = new PluginEvent('beforeSurveyBarRender', $this);
            $event->set('surveyId', $iSurveyID);
            App()->getPluginManager()->dispatchEvent($event);
            $beforeSurveyBarRender = $event->get('menus');
            $aData['beforeSurveyBarRender'] = $beforeSurveyBarRender ? $beforeSurveyBarRender : array();

            // Only show tools menu if at least one item is permitted
            $aData['showToolsMenu'] =
                    $aData['surveydelete']
                || $aData['surveytranslate']
                || Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'update')
                || !is_null($extraToolsMenuItems);

            $iConditionCount = Condition::model()->with(array('questions'=>array('condition'=>'sid ='.$iSurveyID)))->count();

            $aData['surveycontent'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'update');
            $aData['conditionscount'] = ($iConditionCount > 0);
            // Eport menu item
            $aData['surveyexport'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'export');
            // PRINTABLE VERSION OF SURVEY BUTTON
            // SHOW PRINTABLE AND SCANNABLE VERSION OF SURVEY BUTTON
            //browse responses menu item
            $aData['respstatsread'] = Permission::model()->hasSurveyPermission($iSurveyID, 'responses', 'read')
                || Permission::model()->hasSurveyPermission($iSurveyID, 'statistics', 'read')
                || Permission::model()->hasSurveyPermission($iSurveyID, 'responses', 'export');
            // Data entry screen menu item
            $aData['responsescreate'] = Permission::model()->hasSurveyPermission($iSurveyID, 'responses', 'create');
            $aData['responsesread'] = Permission::model()->hasSurveyPermission($iSurveyID, 'responses', 'read');
            // TOKEN MANAGEMENT BUTTON
            if (!$oSurvey->hasTokensTable) {
                $aData['tokenmanagement'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update')
                    || Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'create');
            } else {
                $aData['tokenmanagement'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update')
                    || Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'create')
                    || Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'read')
                    || Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'export')
                    || Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'import'); // and export / import ?
            }

            $aData['gid'] = $gid; // = $this->input->post('gid');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'read')) {
                $aData['permission'] = true;
            } else {
                $aData['gid'] = $gid = null;
                $aData['permission'] = false;
            }

            if (getGroupListLang($gid, $oSurvey->language, $iSurveyID)) {
                $aData['groups'] = getGroupListLang($gid, $oSurvey->language, $iSurveyID);
            } else {
                $aData['groups'] = "<option>".gT("None")."</option>";
            }

            $aData['GidPrev'] = getGidPrevious($iSurveyID, $gid);

            $aData['GidNext'] = getGidNext($iSurveyID, $gid);
            $aData['iIconSize'] = Yii::app()->getConfig('adminthemeiconsize');

            if (isset($aData['surveybar']['closebutton']['url'])) {
                $sAlternativeUrl = $aData['surveybar']['closebutton']['url'];
                $aData['surveybar']['closebutton']['url'] = Yii::app()->request->getUrlReferrer(Yii::app()->createUrl($sAlternativeUrl));
            }

            if ($aData['gid'] == null) {
                            $this->getController()->renderPartial("/admin/survey/surveybar_view", $aData);
            }
        }
    }

    /**
     * Show side menu for survey view
     * @param array $aData all the needed data
     */
    private function _surveysidemenu($aData)
    {
        $iSurveyID = $aData['surveyid'];

        $survey = Survey::model()->findByPk($iSurveyID);
        // TODO : create subfunctions
        $sumresult1 = Survey::model()->with(array(
            'languagesettings' => array('condition'=>'surveyls_language=language'))
        )->find('sid = :surveyid', array(':surveyid' => $aData['surveyid'])); //$sumquery1, 1) ; //Checked

        if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'read')) {
            $aData['permission'] = true;
        } else {
            $aData['gid'] = null;
            $aData['permission'] = false;
        }

        if (!is_null($sumresult1)) {
            // $surveyinfo = $sumresult1->attributes;
            // $surveyinfo = array_merge($surveyinfo, $sumresult1->defaultlanguage->attributes);
            // $surveyinfo = array_map('flattenText', $surveyinfo);
            $aData['activated'] = $survey->isActive;

            // Tokens
            $bTokenExists = $survey->hasTokensTable;
            if (!$bTokenExists) {
                $aData['tokenmanagement'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update')
                    || Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'create');
            } else {
                $aData['tokenmanagement'] = Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update')
                    || Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'create')
                    || Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'read')
                    || Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'export')
                    || Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'import'); // and export / import ?
            }

            // Question explorer
            $aGroups = QuestionGroup::model()->findAllByAttributes(array('sid' => $iSurveyID), array('order'=>'group_order ASC'));
            $aData['quickmenu'] = $this->renderQuickmenu($aData);
            $aData['beforeSideMenuRender'] = $this->beforeSideMenuRender($aData);
            $aData['aGroups'] = $aGroups;
            $aData['surveycontent'] = Permission::model()->hasSurveyPermission($aData['surveyid'], 'surveycontent', 'read');
            $aData['surveycontentupdate'] = Permission::model()->hasSurveyPermission($aData['surveyid'], 'surveycontent', 'update');
            $aData['sideMenuBehaviour'] = getGlobalSetting('sideMenuBehaviour');
            $this->getController()->renderPartial("/admin/super/sidemenu", $aData);
        } else {
            Yii::app()->session['flashmessage'] = gT("Invalid survey ID");
            $this->getController()->redirect(array("admin/index"));
        }
    }

    /**
     * Render the quick-menu that is shown
     * when side-menu is hidden.
     *
     * Only show home-icon for now.
     *
     * Add support for plugin to attach
     * icon elements using event afterQuickMenuLoad
     *
     * @param array $aData
     * @return string
     * @todo Make quick-menu user configurable
     */
    protected function renderQuickmenu(array $aData)
    {
        $event = new PluginEvent('afterQuickMenuLoad', $this);
        $event->set('aData', $aData);
        $result = App()->getPluginManager()->dispatchEvent($event);

        $quickMenuItems = $result->get('quickMenuItems');
        if (!empty($quickMenuItems)) {
            usort($quickMenuItems, function($b1, $b2)
            {
                return (int) $b1['order'] > (int) $b2['order'];
            });
        }

        $aData['quickMenuItems'] = $quickMenuItems;

        if ($aData['quickMenuItems'] === null) {
            $aData['quickMenuItems'] = array();
        }

        $html = $this->getController()->renderPartial('/admin/super/quickmenu', $aData, true);
        return $html;
    }

    /**
     * Returns content from event beforeSideMenuRender
     * @param array $aData
     * @return string
     */
    protected function beforeSideMenuRender(array $aData)
    {
        $event = new PluginEvent('beforeSideMenuRender', $this);
        $event->set('aData', $aData);
        $result = App()->getPluginManager()->dispatchEvent($event);
        return $result->get('html');
    }

    /**
     * listquestion groups
     * @param array $aData
     */
    private function _listquestiongroups(array $aData)
    {
        if (isset($aData['display']['menu_bars']['listquestiongroups'])) {
            $this->getController()->renderPartial("/admin/survey/QuestionGroups/listquestiongroups", $aData);
        }
    }

    private function _listquestions($aData)
    {
        if (isset($aData['display']['menu_bars']['listquestions'])) {
            $iSurveyID = $aData['surveyid'];
            $oSurvey = $aData['oSurvey'];

            // The DataProvider will be build from the Question model, search method
            $model = new Question('search');

            // Global filter
            if (isset($_GET['Question'])) {
                $model->setAttributes($_GET['Question'], false);
            }

            // Filter group
            if (isset($_GET['gid'])) {
                $model->gid = $_GET['gid'];
            }

            // Set number of page
            if (isset($_GET['pageSize'])) {
                Yii::app()->user->setState('pageSize', (int) $_GET['pageSize']);
            }

            // We filter the current survey id
            $model->sid = $iSurveyID;

            $aData['model'] = $model;

            $this->getController()->renderPartial("/admin/survey/Question/listquestions", $aData);
        }
    }

    /**
     * Show survey summary
     * @param array $aData
     */
    public function _surveysummary($aData)
    {
        $iSurveyID = $aData['surveyid'];

        $aSurveyInfo = getSurveyInfo($iSurveyID);
        /** @var Survey $oSurvey */
        $oSurvey = $aData['oSurvey'];
        $activated = $aSurveyInfo['active'];

        $condition = array('sid' => $iSurveyID, 'parent_qid' => 0);
        $sumcount3 = Question::model()->countByAttributes($condition); //Checked
        $sumcount2 = QuestionGroup::model()->countByAttributes(array('sid' => $iSurveyID));

        //SURVEY SUMMARY
        $aAdditionalLanguages = $oSurvey->additionalLanguages;
        $surveysummary2 = [];
        if ($aSurveyInfo['anonymized'] != "N") {
            $surveysummary2[] = gT("Responses to this survey are anonymized.");
        } else {
            $surveysummary2[] = gT("Responses to this survey are NOT anonymized.");
        }
        if ($aSurveyInfo['format'] == "S") {
            $surveysummary2[] = gT("It is presented question by question.");
        } elseif ($aSurveyInfo['format'] == "G") {
            $surveysummary2[] = gT("It is presented group by group.");
        } else {
            $surveysummary2[] = gT("It is presented on one single page.");
        }
        if ($aSurveyInfo['questionindex'] > 0) {
            if ($aSurveyInfo['format'] == 'A') {
                $surveysummary2[] = gT("No question index will be shown with this format.");
            } elseif ($aSurveyInfo['questionindex'] == 1) {
                $surveysummary2[] = gT("A question index will be shown; participants will be able to jump between viewed questions.");
            } elseif ($aSurveyInfo['questionindex'] == 2) {
                $surveysummary2[] = gT("A full question index will be shown; participants will be able to jump between relevant questions.");
            }
        }
        if ($oSurvey->isDateStamp) {
            $surveysummary2[] = gT("Responses will be date stamped.");
        }
        if ($oSurvey->isIpAddr) {
            $surveysummary2[] = gT("IP Addresses will be logged");
        }
        if ($oSurvey->isRefUrl) {
            $surveysummary2[] = gT("Referrer URL will be saved.");
        }
        if ($oSurvey->isUseCookie) {
            $surveysummary2[] = gT("It uses cookies for access control.");
        }
        if ($oSurvey->isAllowRegister) {
            $surveysummary2[] = gT("If tokens are used, the public may register for this survey");
        }
        if ($oSurvey->isAllowSave && !$oSurvey->isTokenAnswersPersistence) {
            $surveysummary2[] = gT("Participants can save partially finished surveys");
        }
        if ($oSurvey->emailnotificationto != '') {
            $surveysummary2[] = gT("Basic email notification is sent to:").' '.htmlspecialchars($aSurveyInfo['emailnotificationto']);
        }
        if ($oSurvey->emailresponseto != '') {
            $surveysummary2[] = gT("Detailed email notification with response data is sent to:").' '.htmlspecialchars($aSurveyInfo['emailresponseto']);
        }

        $dateformatdetails = getDateFormatData(Yii::app()->session['dateformat']);
        if (trim($oSurvey->startdate) != '') {
            Yii::import('application.libraries.Date_Time_Converter');
            $datetimeobj = new Date_Time_Converter($oSurvey->startdate, 'Y-m-d H:i:s');
            $aData['startdate'] = $datetimeobj->convert($dateformatdetails['phpdate'].' H:i');
        } else {
            $aData['startdate'] = "-";
        }

        if (trim($oSurvey->expires) != '') {
            //$constructoritems = array($surveyinfo['expires'] , "Y-m-d H:i:s");
            Yii::import('application.libraries.Date_Time_Converter');
            $datetimeobj = new Date_Time_Converter($oSurvey->expires, 'Y-m-d H:i:s');
            //$datetimeobj = new Date_Time_Converter($surveyinfo['expires'] , "Y-m-d H:i:s");
            $aData['expdate'] = $datetimeobj->convert($dateformatdetails['phpdate'].' H:i');
        } else {
            $aData['expdate'] = "-";
        }

        $aData['language'] = getLanguageNameFromCode($oSurvey->language, false);

        if ($oSurvey->currentLanguageSettings->surveyls_urldescription == "") {
            $aSurveyInfo['surveyls_urldescription'] = htmlspecialchars($aSurveyInfo['surveyls_url']);
        }

        if ($oSurvey->currentLanguageSettings->surveyls_url != "") {
            $aData['endurl'] = " <a target='_blank' href=\"".htmlspecialchars($aSurveyInfo['surveyls_url'])."\" title=\"".htmlspecialchars($aSurveyInfo['surveyls_url'])."\">".flattenText($oSurvey->currentLanguageSettings->surveyls_url)."</a>";
        } else {
            $aData['endurl'] = "-";
        }

        $aData['sumcount3'] = $sumcount3;
        $aData['sumcount2'] = $sumcount2;

        if ($activated == "N") {
            $aData['activatedlang'] = gT("No");
        } else {
            $aData['activatedlang'] = gT("Yes");
        }

        $aData['activated'] = $activated;
        if ($oSurvey->isActive) {
            $aData['surveydb'] = Yii::app()->db->tablePrefix."survey_".$iSurveyID;
        }

        $aData['warnings'] = [];
        if ($activated == "N" && $sumcount3 == 0) {
            $aData['warnings'][] = gT("Survey cannot be activated yet.");
            if ($sumcount2 == 0 && Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'create')) {
                $aData['warnings'][] = "<span class='statusentryhighlight'>[".gT("You need to add survey pages")."]</span>";
            }
            if ($sumcount3 == 0 && Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'create')) {
                $aData['warnings'][] = "<span class='statusentryhighlight'>".gT("You need to add questions")."</span>";
            }
        }
        $aData['hints'] = $surveysummary2;

        //return (array('column'=>array($columns_used,$hard_limit) , 'size' => array($length, $size_limit) ));
        //        $aData['tableusage'] = getDBTableUsage($iSurveyID);
        // ToDo: Table usage is calculated on every menu display which is too slow with big surveys.
        // Needs to be moved to a database field and only updated if there are question/subquestions added/removed (it's currently also not functional due to the port)
        //

        $aData['tableusage'] = false;
        $aData['aAdditionalLanguages'] = $aAdditionalLanguages;
        $aData['groups_count'] = $sumcount2;

        // We get the state of the quickaction
        // If the survey is new (ie: it has no group), it is opened by default
        $quickactionState = SettingsUser::getUserSettingValue('quickaction_state');
        if ($quickactionState === null || $quickactionState === 0) {
            $quickactionState = 1;
            SettingsUser::setUserSetting('quickaction_state', 1);
        }
        $aData['quickactionstate'] = $quickactionState !== null ? intval($quickactionState) : 1;
        $aData['subviewData'] = $aData;

        Yii::app()->getClientScript()->registerPackage('surveysummary');

        $content = $this->getController()->renderPartial("/admin/survey/surveySummary_view", $aData, true);
        $this->getController()->renderPartial("/admin/super/sidebody", array(
            'content' => $content,
            'sideMenuOpen' => true
        ));
    }

    /**
     * Browse Menu Bar
     * @param array $aData
     */
    public function _browsemenubar(array $aData)
    {
        if (!empty($aData['display']['menu_bars']['browse']) && !empty($aData['surveyid'])) {
            //BROWSE MENU BAR
            $iSurveyID = $aData['surveyid'];
            $aData['title'] = $aData['display']['menu_bars']['browse'];
            $aData['thissurvey'] = getSurveyInfo($iSurveyID);
            $aData['surveyid'] = $iSurveyID;

            if (!isset($aData['menu']['closeurl'])) {
                $aData['menu']['closeurl'] = Yii::app()->request->getUrlReferrer(Yii::app()->createUrl("/admin/responses/sa/browse/surveyid/".$aData['surveyid']));
            }

            $this->getController()->renderPartial("/admin/responses/browsemenubar_view", $aData);
        }
    }

    /**
     * Load menu bar of user group controller.
     * @param array $aData
     * @return void
     */
    public function _userGroupBar(array $aData)
    {
        $ugid = (isset($aData['ugid'])) ? $aData['ugid'] : 0;
        if (!empty($aData['display']['menu_bars']['user_group'])) {
            $data = $aData;
            Yii::app()->loadHelper('database');

            if (!empty($ugid)) {
                $userGroup = UserGroup::model()->findByPk($ugid);
                $uid = Yii::app()->session['loginID'];
                if ($userGroup && $userGroup->hasUser($uid)) {
                    $data['userGroup'] = $userGroup;
                } else {
                    $data['userGroup'] = null;
                }
            }

            $data['imageurl'] = Yii::app()->getConfig("adminimageurl");

            if (isset($aData['usergroupbar']['closebutton']['url'])) {
                $sAlternativeUrl = $aData['usergroupbar']['closebutton']['url'];
                $aData['usergroupbar']['closebutton']['url'] = Yii::app()->request->getUrlReferrer(Yii::app()->createUrl($sAlternativeUrl));
            }

            $this->getController()->renderPartial('/admin/usergroup/usergroupbar_view', $data);
        }
    }

    /**
     * @param string $extractdir
     * @param string $destdir
     * @return array
     */
    protected function _filterImportedResources($extractdir, $destdir)
    {
        $aErrorFilesInfo = array();
        $aImportedFilesInfo = array();

        if (!is_dir($extractdir)) {
                    return array(array(), array());
        }

        if (!is_dir($destdir)) {
                    mkdir($destdir);
        }

        $dh = opendir($extractdir);
        if (!$dh) {
            $aErrorFilesInfo[] = array(
                "filename" => '',
                "status" => gT("Extracted files not found - maybe a permission problem?")
            );
            return array($aImportedFilesInfo, $aErrorFilesInfo);
        }
        while ($direntry = readdir($dh)) {
            if ($direntry != "." && $direntry != "..") {
                if (is_file($extractdir."/".$direntry)) {
                    // is  a file
                    $extfile = (string) substr(strrchr($direntry, '.'), 1);
                    if (!(stripos(','.Yii::app()->getConfig('allowedresourcesuploads').',', ','.$extfile.',') === false)) {
                        // Extension allowed
                        if (!copy($extractdir."/".$direntry, $destdir."/".$direntry)) {
                            $aErrorFilesInfo[] = array(
                            "filename" => $direntry,
                            "status" => gT("Copy failed")
                            );
                        } else {
                            $aImportedFilesInfo[] = array(
                            "filename" => $direntry,
                            "status" => gT("OK")
                            );
                        }
                    } else {
                        // Extension forbidden
                        $aErrorFilesInfo[] = array(
                        "filename" => $direntry,
                        "status" => gT("Forbidden Extension")
                        );
                    }
                    unlink($extractdir."/".$direntry);
                }
            }
        }

        return array($aImportedFilesInfo, $aErrorFilesInfo);
    }

    /**
     * Get extra menus from plugins that are using event beforeAdminMenuRender
     *
     * @param array $aData
     * @return array<ExtraMenu>
     */
    protected function fetchExtraMenus(array $aData)
    {
        $event = new PluginEvent('beforeAdminMenuRender', $this);
        $event->set('data', $aData);
        $result = App()->getPluginManager()->dispatchEvent($event);

        $extraMenus = $result->get('extraMenus');

        if ($extraMenus === null) {
            $extraMenus = array();
        }

        return $extraMenus;
    }

    /**
     * Method to render an array as a json document
     *
     * @param array $aData
     * @return void
     */
    protected function renderJSON($aData, $success=true)
    {
        
        $aData['success'] = $aData['success'] ?? $success;

        if (Yii::app()->getConfig('debug') > 0) {
            $aData['debug'] = [$_POST, $_GET];
        }

        echo Yii::app()->getController()->renderPartial('/admin/super/_renderJson', [
            'data' => $aData
        ], true, false);
        return;
    }

}
