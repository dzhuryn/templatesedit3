<?php
/**
 * Created by PhpStorm.
 * @author 64j
 */

class templatesedit
{
    private static $instance = null;
    protected $evo;
    protected $doc;
    protected $params;
    protected $config;
    protected $basePath = MODX_BASE_PATH . 'assets/plugins/templatesedit/';
    protected $richtexteditorOptions = [];
    protected $richtexteditorIds = [];
    protected $default_fields = [];
    protected $added_fields = [];
    protected $categories = [];
    protected $tvars = null;

    public static function getInstance($params = [])
    {
        if (self::$instance === null) {
            self::$instance = new static($params);
        }

        return self::$instance;
    }

    public function __construct($doc = [])
    {
        $this->doc = $doc;
        $this->evo = evolutionCMS();
        $this->params = $this->evo->event->params;
        $this->params['showTvImage'] = isset($this->params['showTvImage']) && $this->params['showTvImage'] == 'yes';
        $this->params['excludeTvCategory'] = !empty($this->params['excludeTvCategory']) ? array_map('trim', explode(',', $this->params['excludeTvCategory'])) : [];
        // default
        $this->params['default.tab'] = false;
        $this->params['role'] = $_SESSION['mgrRole'];
        $this->params['col.settings'] = [];
    }

    public function renderTemplate()
    {
        $this->setDefaults();
        $this->getConfig();
        $this->getTemplateVariables();

        return $this->view('document', [
            'content' => $this->renderTabs()
        ]);
    }

    public function renderAfterTemplate()
    {
        global $richtexteditorIds, $richtexteditorOptions;

        $out = '';

        if (isset($this->config['#Static']) && $tabContent = $this->renderTab($this->config['#Static'])) {
            $out .= $this->view('element', [
                'id' => 'Static',
                'class' => 'col p-1',
                'content' => $this->view('element', [
                    'class' => 'row form-row',
                    'content' => $tabContent
                ])
            ]);
        }

        $default_fields = array_diff(array_keys($this->default_fields), $this->added_fields);
        if ($default_fields) {
            $out .= '<!-- hidden fields -->';
            foreach ($default_fields as $fieldName) {
                if (in_array($fieldName, ['introtext', 'content'])) {
                    continue;
                }
                $out .= $this->form('input', [
                    'type' => 'hidden',
                    'name' => $fieldName,
                    'value' => isset($this->doc[$fieldName]) ? $this->evo->htmlspecialchars($this->doc[$fieldName]) : ''
                ]);
            }
            $out .= '<!-- end hidden fields -->';
        }

        $richtexteditorIds = $this->richtexteditorIds;
        $richtexteditorOptions = $this->richtexteditorOptions;

        return $out;
    }

    protected function setDefaults()
    {
        $this->doc['template'] = $this->getTemplateId();

        if (!isset($_REQUEST['id'])) {
            $this->doc['id'] = 0;
        }

        if (isset($_REQUEST['pid'])) {
            $this->doc['parent'] = $_REQUEST['pid'];
        }

        if (!isset($this->doc['pagetitle'])) {
            $this->doc['pagetitle'] = '';
        }

        if (!isset($this->doc['longtitle'])) {
            $this->doc['longtitle'] = '';
        }

        if (!isset($this->doc['description'])) {
            $this->doc['description'] = '';
        }

        if (!isset($this->doc['menutitle'])) {
            $this->doc['menutitle'] = '';
        }

        if (!isset($this->doc['introtext'])) {
            $this->doc['introtext'] = '';
        }

        if (!isset($this->doc['content'])) {
            $this->doc['content'] = '';
        }

        if (!isset($this->doc['alias'])) {
            $this->doc['alias'] = '';
        }

        if (!isset($this->doc['link_attributes'])) {
            $this->doc['link_attributes'] = '';
        }

        if (!isset($this->doc['contentType'])) {
            $this->doc['contentType'] = 'text/html';
        }

        if ($this->evo->manager->action == 85 || (isset($_REQUEST['isfolder']) && $_REQUEST['isfolder'] == 1)) {
            $this->doc['isfolder'] = 1;
        }

        if ($this->evo->manager->action == 85 || $this->evo->manager->action == 4) {
            $this->doc['type'] = 'document';
        }

        if ($this->evo->manager->action == 72) {
            $this->doc['type'] = 'reference';
        }

        if ((isset($this->doc['published']) && $this->doc['published'] == 1) || (!isset($this->doc['published']) && $this->evo->config['publish_default'] == 1)) {
            $this->doc['published'] = 1;
        }

        if (!isset($this->doc['alias_visible'])) {
            $this->doc['alias_visible'] = 1;
        }

        if ((isset($this->doc['searchable']) && $this->doc['searchable'] == 1) || (!isset($this->doc['searchable']) && $this->evo->config['search_default'] == 1)) {
            $this->doc['searchable'] = 1;
        }

        if ((isset($this->doc['cacheable']) && $this->doc['cacheable'] == 1) || (!isset($this->doc['cacheable']) && $this->evo->config['cache_default'] == 1)) {
            $this->doc['cacheable'] = 1;
        }

        if (isset($this->doc['richtext']) && $this->doc['richtext'] == 0 && $this->evo->manager->action == 27) {
            $this->doc['richtext'] = 0;
        } else {
            $this->doc['richtext'] = 1;
        }

        $this->doc['syncsite'] = 1;

        $this->default_fields = require_once $this->basePath . 'configs/fields.php';

        if (file_exists($this->basePath . 'configs/custom_fields.php')) {
            $this->default_fields += require_once $this->basePath . 'configs/custom_fields.php';
        }
    }

    protected function getTemplateId()
    {
        if (isset($_REQUEST['newtemplate'])) {
            $this->doc['template'] = $_REQUEST['newtemplate'];
        } elseif (!isset($this->doc['template'])) {
            $this->doc['template'] = getDefaultTemplate();
        }

        return $this->doc['template'];
    }

    protected function getConfig()
    {
        $this->config = [];
        $json = '';

        if (file_exists($this->basePath . 'configs/template__' . $this->doc['template'] . '__' . $this->params['role'] . '.json')) {
            $json = $this->basePath . 'configs/template__' . $this->doc['template'] . '__' . $this->params['role'] . '.json';
        } elseif (file_exists($this->basePath . 'configs/template__' . $this->doc['template'] . '__1.json')) {
            $json = $this->basePath . 'configs/template__' . $this->doc['template'] . '__1.json';
        } elseif ($file = glob($this->basePath . 'configs/template_*_default.json')) {
            $json = $file[0];
        }

        if ($json) {
            $this->config = json_decode(file_get_contents($json), true);
        } else {
            $docTemplate = $this->evo->db->getRow($this->evo->db->select('*',$this->evo->getFullTableName('site_templates'),'id = '.intval($this->doc['template'])));

            if(isset($docTemplate['templatealias']) && file_exists($this->basePath . 'configs/template__' . $docTemplate['templatealias'] . '.php')){
                $this->config = require_once $this->basePath . 'configs/template__' . $docTemplate['templatealias'] . '.php';
            }
            elseif (file_exists($this->basePath . 'configs/template__' . $this->doc['template'] . '.php')) {
                $this->config = require_once $this->basePath . 'configs/template__' . $this->doc['template'] . '.php';
            } else {
                $this->config = require_once $this->basePath . 'configs/template__default.php';
            }
        }

        return $this->config;
    }

    public function getTemplateVariables()
    {
        if (is_array($this->tvars)) {
            return $this->tvars;
        }

        $docgrp = '';
        if (!empty($_SESSION['mgrDocgroups'])) {
            $docgrp = implode(',', $_SESSION['mgrDocgroups']);
        }

        $sql = $this->evo->db->select('
        DISTINCT tv.*, IF(tvc.value!="",tvc.value,tv.default_text) as value, tvtpl.rank', $this->evo->getFullTableName('site_tmplvars') . ' AS tv
        INNER JOIN ' . $this->evo->getFullTableName('site_tmplvar_templates') . ' AS tvtpl ON tvtpl.tmplvarid = tv.id
        LEFT JOIN ' . $this->evo->getFullTableName('site_tmplvar_contentvalues') . ' AS tvc ON tvc.tmplvarid=tv.id AND tvc.contentid="' . $this->doc['id'] . '"
        LEFT JOIN ' . $this->evo->getFullTableName('site_tmplvar_access') . ' AS tva ON tva.tmplvarid=tv.id', 'tvtpl.templateid="' . $this->doc['template'] . '" AND (1="' . $_SESSION['mgrRole'] . '" OR ISNULL(tva.documentgroup)' . (!$docgrp ? '' : ' OR tva.documentgroup IN (' . $docgrp . ')') . ')', 'tvtpl.rank, tv.rank, tv.id');

        if ($this->evo->db->getRecordCount($sql)) {
            while ($row = $this->evo->db->getRow($sql)) {
                $this->categories[$row['category']][$row['name']] = $row;
                $this->tvars[$row['name']] = $row;
            }
        }

        $categories = $this->categories;

        foreach ($this->config as $tabId => &$tab) {
            if (!empty($tab['default'])) {
                $this->params['default.tab'] = $tabId;
            }

            if (isset($tab['fields'])) {
                $tab['col:0:12']['fields:0'] = $tab['fields'];
                unset($tab['fields']);
            }

            foreach ($tab as $colId => $col) {
                if (is_array($col)) {
                    foreach ($col as $fieldsId => $fields) {
                        if (substr($fieldsId, 0, 7) == 'fields:') {
                            foreach ($fields as $key => $field) {
                                if (isset($this->tvars[$key])) {
                                    unset($categories[$this->tvars[$key]['category']][$key]);
                                    unset($this->categories[$this->tvars[$key]['category']][$key]);
                                }
                            }
                        }
                        if (substr($fieldsId, 0, 9) == 'category:') {
                            list($category, $categoryId) = explode(':', $fieldsId);
                            unset($categories[$categoryId]);
                            if (empty($this->categories[$categoryId])) {
                                unset($this->config[$tabId][$colId][$fieldsId]);
                            }
                        }
                    }
                    if (empty($this->config[$tabId][$colId])) {
                        unset($this->config[$tabId]);
                    }
                }
            }
        }

        if (!empty($categories) && !empty($this->params['default.tab'])) {
            if (!isset($this->config[$this->params['default.tab']]['col:0:12']['fields:0'])) {
                $this->config[$this->params['default.tab']]['col:0:12']['fields:0'] = [];
            }
            foreach ($categories as $k => $fields) {
                if (!in_array($k, $this->params['excludeTvCategory'])) {
                    foreach ($fields as $key => $field) {
                        $this->config[$this->params['default.tab']]['col:0:12']['fields:0'] += [$key => $field];
                    }
                    unset($this->config[$this->params['default.tab']]['col:0:12']['category:' . $k]);
                }
            }
        }

        return $this->tvars;
    }

    protected function renderTabs()
    {
        $out = '';
        $this->added_fields = [];

        require_once MODX_MANAGER_PATH . 'includes/tmplvars.inc.php';
        require_once MODX_MANAGER_PATH . 'includes/tmplvars.commands.inc.php';

        if (($this->doc['richtext'] == 1 || $this->evo->manager->action == 4) && $this->evo->config['use_editor'] == 1 && $this->doc['type'] == 'document') {
            $this->richtexteditorIds[$this->evo->config['which_editor']][] = 'ta';
            $this->richtexteditorOptions[$this->evo->config['which_editor']]['ta'] = '';
        }

        foreach ($this->config as $tabName => &$tab) {
            if ($tab['title'] && $tabName != '#Static') {
                $tabContent = $this->renderTab($tab);
                if ($tabContent) {
                    $out .= $this->view('tab', [
                        'name' => $tabName,
                        'title' => $tab['title'],
                        'tabsObject' => 'tpSettings',
                        'content' => $this->view('element', [
                            'class' => 'row form-row',
                            'content' => $tabContent
                        ])
                    ]);
                } else {
                    unset($this->config[$tabName]);
                }
            }
        }

        return $out;
    }

    protected function renderTab($tab, $settings = [])
    {
        $out = '';
        foreach ($tab as $k => $fields) {
            list($type, $id) = explode(':', $k . ':');
            switch ($type) {
                case 'category':
                    if (!empty($this->categories[$id])) {
                        $out .= $this->renderTab([
                            'fields' => $this->categories[$id]
                        ], $settings);
                    }
                    break;

                case 'col':
                    $out .= $this->renderCol($fields, $k);
                    break;

                case 'fields':
                    $out .= $this->renderFields($fields, $k, $settings);
                    break;

                default:
                    break;
            }
        }

        return $out;
    }

    protected function renderCol($data = [], $key = '')
    {
        $settings = [];
        $title = '';
        list($type, $id, $col) = explode(':', $key);

        if (isset($data['settings'])) {
            $settings = $data['settings'];

            if (isset($settings['title']) && $settings['title'] != '') {
                $title = $this->view('element', [
                    'class' => 'col-12',
                    'content' => $settings['title']
                ]);
            }

            unset($data['settings']);
        }

        return $this->view('element', [
            'class' => 'row-col col-lg-' . $col . ($col > 3 && $col < 9 ? ' col-md-6 col-12' : ' col-12'),
            'content' => $title . $this->renderTab($data, $settings)
        ]);
    }

    protected function renderFields($fields, $tabName, $settings = [])
    {
        $out = '';

        foreach ($fields as $fieldName => $field) {
            if (isset($this->tvars[$fieldName])) {
                $field = array_merge($this->tvars[$fieldName], $field);
            }
            if (!isset($field['class'])) {
                $field['class'] = '';
            }
            if (isset($field['type']) && ($field['type'] == 'split' || $field['type'] == 'splitter')) {
                $out .= $field['title'];
            } else {
                if (empty($this->doc['id']) && !isset($field['id'])) {
                    if (isset($field['default'])) {
                        $this->doc[$fieldName] = $field['default'];
                    }
                }
                if (!isset($field['id']) && !isset($this->default_fields[$fieldName])) {
                    unset($this->config[$tabName]['fields'][$fieldName]);
                } else {
                    if (isset($this->default_fields[$fieldName])) {
                        if (!isset($field['title'])) {
                            $field['title'] = $this->default_fields[$fieldName]['title'];
                        }
                        if (!isset($field['help'])) {
                            $field['help'] = $this->default_fields[$fieldName]['help'];
                        }
                        array_push($this->added_fields, $fieldName);
                    }
                    $render_field = $this->renderField($fieldName, $field, $settings);
                    if ($render_field) {
                        $out .= $render_field;
                    }
                }
            }
        }

        return $out;
    }

    protected function renderField($key, $data, $settings = [])
    {
        global $_lang;

        $out = '';
        $field = '';
        $value = '';
        $rowClass = '';
        $leftClass = '';
        $rightClass = '';
        $labelFor = $key;
        $isTv = false;

        $data['id'] = isset($data['id']) ? $data['id'] : $key;
        $data['name'] = isset($data['name']) ? $data['name'] : $key;
        $data['title'] = isset($data['title']) ? $data['title'] : '';
        $data['caption'] = isset($data['caption']) ? $data['caption'] : '';
        $data['help'] = isset($data['help']) && $data['help'] != '' ? '<i class="fa fa-question-circle" data-tooltip="' . stripcslashes($data['help']) . '"></i>' : '';
        $data['description'] = isset($data['description']) && $data['description'] != '' ? '<span class="comment d-block">' . $data['description'] . '</span>' : '';
        $data['pattern'] = isset($data['pattern']) ? ' pattern="' . $data['pattern'] . '"' : '';
        $data['required'] = !empty($data['required']) ? ' required' : '';
        $data['elements'] = isset($data['elements']) ? $data['elements'] : '';

        if (isset($this->default_fields[$key])) {
            if (isset($data['type']) && $key != 'weblink') {
                $rowClass .= ' form-row-' . $data['type'];
                $data['default'] = isset($data['default']) ? $data['default'] : '';
                $data['value'] = isset($this->doc[$key]) ? $this->doc[$key] : $data['default'];
                $field = renderFormElement($data['type'], $key, $data['default'], $data['elements'], $data['value'], '', $data);
                $field = str_replace([' id="tv', ' name="tv'], [' id="', $data['required'] . ' name="'], $field);
                if (!empty($data['rows']) && is_numeric($data['rows'])) {
                    $field = preg_replace('/rows="(.*?)"/is', 'rows="' . $data['rows'] . '"', $field);
                }
                switch ($data['type']) {
                    case 'date':
                        $field = str_replace(['DatePicker', 'onclick="', '.elements[\'tv'], ['form-control DatePicker', 'class="form-control" onclick="', '.elements[\''], $field);
                        break;

                    case 'text':
                    case 'textarea':
                    case 'textareamini':
                    case 'richtext':
                    case 'listbox':
                    case 'listbox-multiple':
                    case 'option':
                    case 'checkbox':
                    case 'number':
                        $field = str_replace('name="', 'class="form-control" name="', $field);
                        break;

                    case 'image':
                    case 'file':
                        $field = str_replace(['name="', 'type="button"', 'BrowseServer(\'tv', 'BrowseFileServer(\'tv'], ['class="form-control" name="', 'class="form-control" type="button"', 'BrowseServer(\'', 'BrowseFileServer(\''], $field);
                        break;

                    case 'dropdown':
                        $field = str_replace(['name="', 'size="1"'], ['class="form-control" name="', ''], $field);
                        break;

                    default:
                        break;
                }

                if ($data['type'] == 'richtext' || $data['type'] == 'htmlarea') {
                    $tvOptions = $this->evo->parseProperties($data['elements']);
                    $editor = $this->evo->config['which_editor'];
                    if (!empty($tvOptions)) {
                        $editor = isset($tvOptions['editor']) ? $tvOptions['editor'] : $this->evo->config['which_editor'];
                    };
                    $this->richtexteditorIds[$editor][] = $key;
                    $this->richtexteditorOptions[$editor][$key] = $tvOptions;
                }
            } else {
                switch ($key) {
                    case 'published':
                    case 'richtext':
                    case 'donthit':
                    case 'searchable':
                    case 'cacheable':
                    case 'syncsite':
                    case 'alias_visible':
                    case 'isfolder':
                    case 'hidemenu':
                        $rowClass .= ' form-row-checkbox';
                        $labelFor .= 'check';
                        $value = empty($this->doc[$key]) ? 0 : 1;
                        if ($key == 'donthit' || $key == 'hidemenu') {
                            $checked = !$value ? 'checked' : '';
                        } else {
                            $checked = $value ? 'checked' : '';
                        }
                        $field .= $this->form('input', [
                            'type' => 'checkbox',
                            'name' => $key . 'check',
                            'class' => 'form-checkbox form-control ' . $data['class'],
                            'attr' => 'onclick="changestate(document.mutate.' . $key . ');" ' . $checked . $data['required']
                        ]);
                        $field .= $this->form('input', [
                            'type' => 'hidden',
                            'name' => $key,
                            'value' => $value
                        ]);
                        break;

                    case 'pub_date':
                    case 'unpub_date':
                    case 'createdon':
                    case 'editedon':
                        $rowClass .= ' form-row-date';
                        $field .= $this->form('date', [
                            'name' => $key,
                            'value' => ((isset($this->doc[$key]) && $this->doc[$key] == 0) || !isset($this->doc[$key]) ? '' : $this->evo->toDateFormat($this->doc[$key])),
                            'class' => $data['class'],
                            'placeholder' => $this->evo->config['datetime_format'] . ' HH:MM:SS',
                            'icon' => 'fa fa-calendar-times-o',
                            'icon.title' => $_lang['remove_date']
                        ]);
                        break;

                    case 'menusort':
                    case 'menuindex':
                        $rightClass .= 'input-group';
                        $field .= $this->view('element', [
                                'class' => 'input-group-prepend',
                                'content' => $this->view('element', [
                                        'tag' => 'span',
                                        'class' => 'btn btn-secondary',
                                        'attr' => 'onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+\'\')-1;elm.value=v>0? v:0;elm.focus();documentDirty=true;return false;" style="cursor: pointer;"',
                                        'content' => '<i class="fa fa-angle-left"></i>'
                                    ]) . $this->view('element', [
                                        'tag' => 'span',
                                        'class' => 'btn btn-secondary',
                                        'attr' => 'onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+\'\')+1;elm.value=v>0? v:0;elm.focus();documentDirty=true;return false;" style="cursor: pointer;"',
                                        'content' => '<i class="fa fa-angle-right"></i>'
                                    ])
                            ]) . $this->form('input', [
                                'name' => $key,
                                'value' => $this->doc['menuindex'],
                                'maxlength' => 6
                            ]);
                        break;

                    case 'introtext':
                        $field .= $this->form('textarea', [
                            'name' => 'introtext',
                            'value' => $this->evo->htmlspecialchars(stripslashes($this->doc['introtext'])),
                            'class' => $data['class'],
                            'rows' => empty($data['rows']) ? 3 : $data['rows']
                        ]);
                        break;

                    case 'content':
                        if ($this->doc['type'] != 'reference') {
                            $field .= $this->form('textarea', [
                                'name' => 'ta',
                                'value' => $this->evo->htmlspecialchars(stripslashes($this->doc['content'])),
                                'class' => $data['class'],
                                'rows' => empty($data['rows']) ? 20 : $data['rows']
                            ]);
                        }
                        break;

                    case 'weblink':
                        if ($this->doc['type'] == 'reference') {
                            $field .= $this->view('element', [
                                'tag' => 'i',
                                'id' => 'llock',
                                'class' => 'fa fa-chain',
                                'attr' => 'onclick="enableLinkSelection(!allowLinkSelection);"'
                            ]);
                            $field .= $this->form('input', [
                                'name' => 'ta',
                                'value' => !empty($this->doc['content']) ? stripslashes($this->doc['content']) : 'http://',
                                'class' => $data['class']
                            ]);
                        }
                        break;

                    case 'template':
                        $rs = $this->evo->db->select('t.templatename, t.id, c.category', $this->evo->getFullTableName('site_templates') . ' AS t LEFT JOIN ' . $this->evo->getFullTableName('categories') . ' AS c ON t.category = c.id', 't.selectable=1', 'c.category, t.templatename ASC');
                        $optgroup = [];
                        while ($row = $this->evo->db->getRow($rs)) {
                            $category = !empty($row['category']) ? $row['category'] : $_lang['no_category'];
                            $optgroup[$category][$row['id']] = $row['templatename'] . ' (' . $row['id'] . ')';
                        }
                        $field .= $this->form('select', [
                            'name' => 'template',
                            'value' => $this->doc['template'],
                            'options' => [
                                0 => '(blank)'
                            ],
                            'optgroup' => $optgroup,
                            'class' => $data['class'],
                            'onchange' => 'templateWarning();'
                        ]);
                        break;

                    case 'parent':
                        $parentlookup = false;
                        $parentname = $this->evo->config['site_name'];
                        if (!empty($_REQUEST['id']) && !empty($this->doc['parent'])) {
                            $parentlookup = $this->doc['parent'];
                        } elseif (!empty($_REQUEST['pid'])) {
                            $parentlookup = $_REQUEST['pid'];
                        } elseif (!empty($_POST['parent'])) {
                            $parentlookup = $_POST['parent'];
                        } else {
                            $this->doc['parent'] = 0;
                        }
                        if ($parentlookup !== false && is_numeric($parentlookup)) {
                            $rs = $this->evo->db->select('pagetitle', $this->evo->getFullTableName('site_content'), "id='{$parentlookup}'");
                            $parentname = $this->evo->db->getValue($rs);
                            if (!$parentname) {
                                $this->evo->webAlertAndQuit($_lang["error_no_parent"]);
                            }
                        }
                        $field .= $this->view('element', [
                            'class' => 'form-control ' . $data['class'],
                            'content' => $this->view('element', [
                                    'tag' => 'i',
                                    'id' => 'plock',
                                    'class' => 'fa fa-folder-o',
                                    'attr' => 'onclick="enableParentSelection(!allowParentSelection);"'
                                ]) . $this->view('element', [
                                    'tag' => 'b',
                                    'id' => 'parentName',
                                    'content' => $this->doc['parent'] . ' (' . $parentname . ')'
                                ]) . $this->view('input', [
                                    'type' => 'hidden',
                                    'name' => 'parent',
                                    'value' => $this->doc['parent']
                                ])
                        ]);
                        break;

                    case 'type':
                        if ($_SESSION['mgrRole'] == 1 || $this->evo->manager->action != 27 || $_SESSION['mgrInternalKey'] == $this->doc['createdby']) {
                            $field .= $this->form('select', [
                                'name' => 'type',
                                'value' => $this->doc['type'],
                                'options' => [
                                    'document' => $_lang["resource_type_webpage"],
                                    'reference' => $_lang["resource_type_weblink"]
                                ],
                                'class' => $data['class']
                            ]);
                        } else {
                            $field .= $this->form('input', [
                                'type' => 'hidden',
                                'name' => 'type',
                                'value' => $this->doc['type'] != 'reference' && $this->evo->manager->action != 72 ? 'document' : 'reference'
                            ]);
                        }
                        break;

                    case 'contentType':
                        if ($_SESSION['mgrRole'] == 1 || $this->evo->manager->action != 27 || $_SESSION['mgrInternalKey'] == $this->doc['createdby']) {
                            $custom_contenttype = isset($this->evo->config['custom_contenttype']) ? $this->evo->config['custom_contenttype'] : 'text/html,text/plain,text/xml';
                            $options = explode(',', $custom_contenttype);
                            $field .= $this->form('select', [
                                'name' => 'contentType',
                                'value' => isset($this->doc['contentType']) ? $this->doc['contentType'] : 'text/html',
                                'options' => array_combine($options, $options),
                                'class' => $data['class']
                            ]);
                        } else {
                            $field .= $this->form('input', [
                                'type' => 'hidden',
                                'name' => 'type',
                                'value' => $this->doc['type'] == 'reference' ? 'text/html' : (isset($this->doc['contentType']) ? $this->doc['contentType'] : 'text/html')
                            ]);
                            if ($this->doc['type'] == 'reference') {
                                $field .= $this->form('input', [
                                    'type' => 'hidden',
                                    'name' => 'contentType',
                                    'value' => 'text/html'
                                ]);
                            } else {
                                $field .= $this->form('input', [
                                    'type' => 'hidden',
                                    'name' => 'contentType',
                                    'value' => isset($this->doc['contentType']) ? $this->doc['contentType'] : 'text/html'
                                ]);
                            }
                        }
                        break;

                    case 'content_dispo':
                        if ($_SESSION['mgrRole'] == 1 || $this->evo->manager->action != 27 || $_SESSION['mgrInternalKey'] == $this->doc['createdby']) {
                            $field .= $this->form('select', [
                                'name' => 'content_dispo',
                                'value' => isset($this->doc['content_dispo']) ? $this->doc['content_dispo'] : 0,
                                'options' => [
                                    0 => $_lang['inline'],
                                    1 => $_lang['attachment']
                                ],
                                'class' => $data['class']
                            ]);
                        } else {
                            if ($this->doc['type'] != 'reference') {
                                $field .= $this->form('input', [
                                    'type' => 'hidden',
                                    'name' => 'content_dispo',
                                    'value' => isset($this->doc['content_dispo']) ? $this->doc['content_dispo'] : 0
                                ]);
                            }
                        }
                        break;

                    default:
                        $field .= $this->form('input', [
                            'name' => $key,
                            'value' => $this->evo->htmlspecialchars(stripslashes($this->doc[$key])),
                            'class' => 'form-control ' . $data['class'],
                            'attr' => 'spellcheck="true"' . $data['required'] . $data['pattern']
                        ]);
                        break;
                }
            }
        } else {
            $isTv = true;
            $labelFor = 'tv' . $data['id'];
            $rowClass .= ' form-row-' . $data['type'];

            if ($data['title'] == '') {
                $data['title'] = $data['caption'];
                if (substr($data['value'], 0, 8) == '@INHERIT') {
                    $data['description'] .= '<div class="comment inherited">(' . $_lang['tmplvars_inherited'] . ')</div>';
                }
            }

            if (array_key_exists('tv' . $data['id'], $_POST)) {
                if ($data['type'] == 'listbox-multiple') {
                    $data['value'] = implode('||', $_POST['tv' . $data['id']]);
                } else {
                    $data['value'] = $_POST['tv' . $data['id']];
                }
            }

            $field = renderFormElement($data['type'], $data['id'], $data['default_text'], $data['elements'], $data['value'], '', $data);

            if ($data['type'] == 'richtext' || $data['type'] == 'htmlarea') {
                $tvOptions = $this->evo->parseProperties($data['elements']);
                $editor = $this->evo->config['which_editor'];
                if (!empty($tvOptions)) {
                    $editor = isset($tvOptions['editor']) ? $tvOptions['editor'] : $this->evo->config['which_editor'];
                };
                $this->richtexteditorIds[$editor][] = 'tv' . $data['id'];
                $this->richtexteditorOptions[$editor]['tv' . $data['id']] = $tvOptions;
            }

            if (stripos($data['type'], 'custom_tv') === false) {
                // add class form-control
                if ($data['type'] == 'date') {
                    $field = str_replace('class="', 'class="form-control ', $field);
                } else {
                    $field = str_replace(['name="', 'type="button"'], ['class="form-control" name="', 'class="form-control" type="button"'], $field);
                }

                // show required
                if ($data['required']) {
                    $field = str_replace(' name="', $data['required'] . ' name="', $field);
                }
            }
        }

        if ($field) {
            $title = '';
            $data['size'] = !empty($data['size']) ? ' input-group-' . $data['size'] : (!empty($settings['size']) ? ' input-group-' . $settings['size'] : '');
            $data['position'] = !empty($data['position']) ? $data['position'] : (!empty($settings['position']) ? $settings['position'] : '');
            $data['reverse'] = !empty($data['reverse']) ? $data['reverse'] : (!empty($settings['reverse']) ? $settings['reverse'] : '');

            if (trim($data['title'])) {
                $title = '<label for="' . $labelFor . '" class="warning" data-key="' . $key . '">' . $data['title'] . '</label>' . $data['help'] . $data['description'];
                if ($data['position'] == 'c') {
                    $leftClass .= ' col-xs-12 col-12';
                    $rightClass .= ' col-xs-12 col-12';
                    if ($data['reverse']) {
                        $rowClass .= ' column-reverse';
                    }
                } elseif ($data['position'] == 'r') {
                    $leftClass .= ' col';
                    $rightClass .= ' col-auto';
                    if ($data['reverse']) {
                        $rowClass .= ' row-reverse';
                    }
                } elseif ($data['position'] == 'a') {
                    $leftClass .= ' col-12';
                    $rightClass .= ' col-12';
                    $rowClass .= ' col-12 col-sm-6 col-md';
                    if ($data['reverse']) {
                        $rowClass .= ' column-reverse';
                    }
                } else {
                    $leftClass .= ' col-auto col-title';
                    $rightClass .= ' col';
                    if ($data['reverse']) {
                        $rowClass .= ' row-reverse';
                    }
                }
                $rightClass .= $data['size'];
            } else {
                if ($data['position'] == 'a') {
                    $leftClass .= ' col-12';
                    $rightClass .= ' col-12';
                    $rowClass .= ' col-12 col-sm-6 col-md';
                } else {
                    $leftClass .= ' col-xs-12 col-12';
                    $rightClass .= ' col-xs-12 col-12' . $data['size'];
                }
            }

            // show tv image
            if (!empty($data['type']) && $data['type'] == 'image' && $this->params['showTvImage']) {
                $field .= $this->form('thumb', [
                    'name' => $isTv ? 'tv' . $data['id'] : $key,
                    'value' => $isTv ? ($data['value'] ? MODX_SITE_URL . $data['value'] : '') : ($value ? MODX_SITE_URL . $value : ''),
                    'width' => $this->evo->config['thumbWidth']
                ]);
            }

            // show datalist
            if (!empty($data['type']) && ($data['type'] == 'text' || $data['type'] == 'number') && $data['elements']) {
                $options = explode('||', $data['elements']);
                $field .= $this->form('datalist', [
                    'id' => $data['id'],
                    'class' => '',
                    'options' => array_combine($options, $options)
                ]);
            }

            // show choices
            if (isset($data['choices']) && $data['choices'] != '') {
                $field .= $this->showChoices($data['id'], $data['value'], $data['choices']);
            }

            // show select richtext
            if ($key == 'content') {
                $options = [
                    'none' => $_lang['none']
                ];

                $evtOut = $this->evo->invokeEvent("OnRichTextEditorRegister");
                if (is_array($evtOut)) {
                    for ($i = 0; $i < count($evtOut); $i++) {
                        $editor = $evtOut[$i];
                        $options[$editor] = $editor;
                    }
                }

                $field = $this->view('element', [
                        'class' => 'select-which-editor',
                        'content' => $this->form('select', [
                            'name' => 'which_editor',
                            'value' => $this->evo->config['which_editor'],
                            'options' => $options,
                            'class' => 'form-control form-control-sm',
                            'onchange' => 'changeRTE();'
                        ])
                    ]) . $field;
            }

            if ($title) {
                $title = $this->view('element', [
                    'class' => trim($leftClass),
                    'content' => $title
                ]);
            }

            $field = $this->view('element', [
                'class' => trim($rightClass),
                'content' => $field
            ]);

            $out = $this->view('element', [
                'class' => 'row form-row' . $rowClass,
                'content' => $title . $field
            ]);
        }

        return $out;
    }

    protected function form($tpl, $data = [])
    {
        if (!isset($data['name'])) {
            $data['name'] = '';
        }

        if (!isset($data['type'])) {
            $data['type'] = 'text';
        }

        if (!isset($data['id'])) {
            $data['id'] = $data['name'];
        }

        if (!isset($data['value'])) {
            $data['value'] = '';
        }

        if (!isset($data['placeholder'])) {
            $data['placeholder'] = '';
        }

        if (!isset($data['maxlength'])) {
            $data['maxlength'] = '255';
        }

        if (empty($data['class'])) {
            $data['class'] = 'form-control';
        }

        if (!isset($data['attr'])) {
            $data['attr'] = '';
        }

        if (!isset($data['checked'])) {
            $data['checked'] = '';
        } else {
            if (is_bool($data['checked']) || is_numeric($data['checked'])) {
                $data['checked'] = $data['checked'] ? 'checked' : '';
            }
        }

        if (!isset($data['disabled'])) {
            $data['disabled'] = '';
        } else {
            if (is_bool($data['disabled']) || is_numeric($data['disabled'])) {
                $data['disabled'] = $data['disabled'] ? 'disabled' : '';
            }
        }

        $options = '';

        if (!empty($data['options'])) {
            $tmp = $data['options'];
            foreach ($tmp as $k => $v) {
                $options .= $this->form('option', [
                    'value' => $k,
                    'title' => $v,
                    'selected' => $k == $data['value'] ? 'selected' : ''
                ]);
            }
        }

        if (!empty($data['optgroup'])) {
            foreach ($data['optgroup'] as $label => $group) {
                $options .= '<optgroup label="' . $label . '">';
                foreach ($group as $value => $title) {
                    $options .= $this->form('option', [
                        'value' => $value,
                        'title' => $title,
                        'selected' => $value == $data['value'] ? 'selected' : ''
                    ]);
                }
                $options .= '</optgroup>';
            }
        }

        $data['options'] = $options;

        if (!isset($data['onchange'])) {
            $data['onchange'] = 'documentDirty=true;';
        }

        return $this->view($tpl, $data);
    }

    protected function view($tpl, $data = [])
    {
        $tpl = trim($tpl, '/');
        $tpl = $this->basePath . 'tpl/' . $tpl . '.tpl.php';
        if (file_exists($tpl)) {
            extract($data);
            ob_start();
            @require($tpl);
            $out = ob_get_contents();
            ob_end_clean();
        } else {
            $out = 'Error: Could not load template ' . $tpl . '!<br>';
        }

        return $out;
    }

    protected function showChoices($id, $value = '', $separator = ', ')
    {
        $out = '';
        $separator = is_bool($separator) ? ', ' : $this->evo->htmlspecialchars($separator);

        $rs = $this->evo->db->query('
        SELECT value 
        FROM ' . $this->evo->getFullTableName('site_tmplvar_contentvalues') . '
        WHERE tmplvarid=' . $id . '
        GROUP BY value
        ORDER BY value ASC
        ');

        if ($this->evo->db->getRecordCount($rs)) {
            $out .= '<div class="choicesList" data-target="tv' . $id . '" data-separator="' . $separator . '">';
            $separator = trim(htmlspecialchars_decode($separator));
            $value = trim($value, $separator);
            $list = [];
            while ($row = $this->evo->db->getRow($rs)) {
                $list = array_merge($list, array_map('trim', explode($separator, $row['value'])));
            }
            $list = array_unique($list);
            sort($list);
            $value = array_map('trim', explode($separator, $value));
            foreach ($list as $val) {
                if (trim($val) != '') {
                    if (in_array($val, $value)) {
                        $out .= '<i class="selected">' . $val . '</i>';
                    } else {
                        $out .= '<i>' . $val . '</i>';
                    }
                }
            }
            $out .= '</div>';
        }

        return $out;
    }

    public function OnDocFormSave($id, $mode)
    {
        if (!empty($id)) {
            $data = [];

            if (file_exists($this->basePath . 'configs/custom_fields.php')) {
                $custom_fields = require_once $this->basePath . 'configs/custom_fields.php';
                if (is_array($custom_fields)) {
                    foreach ($custom_fields as $k => $v) {
                        if (!empty($v['save'])) {
                            if (isset($_REQUEST[$k])) {
                                $v = $_REQUEST[$k];
                                if (is_array($v)) {
                                    $v = implode('||', $v);
                                }
                                $data[$k] = $this->evo->db->escape($v);
                            } else {
                                $data[$k] = isset($v['default']) ? $v['default'] : '';
                            }
                        }
                    }
                }
            }

            if (!empty($data)) {
                $this->evo->db->update($data, '[+prefix+]site_content', 'id=' . $id);
            }
        }
    }

    public function getDocument()
    {
        return $this->doc;
    }

    public function getCategories()
    {
        return $this->categories;
    }

    protected function dd($str = '', $exit = false)
    {
        print '<pre>';
        print_r($str);
        print '</pre>';
        if ($exit) {
            exit;
        }
    }
}
