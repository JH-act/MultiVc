<?php

/**
 * MultiVc plugin: report logged max concurrent values table GUI
 *
 * @author Uwe Kohnle <kohnle@internetlehrer-gmbh.de>
 * @version $Id$
 */
class ilMultiVcTableGUIRecordingsVVD extends ilTable2GUI
{
    private ILIAS\DI\Container $dic;
    protected ?object $parent_obj;

    public function __construct(object $a_parent_obj, string $a_parent_cmd = '', string $a_template_context = '')
    {
        global $DIC;
        $this->dic = $DIC;
        $this->parent_obj = $a_parent_obj;

        $this->setId('table_recordings');
        parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);

        $this->addColumn($this->dic->language()->txt('rep_robj_xmvc_select'), '', '5%');
        $this->addColumn($this->dic->language()->txt('rep_robj_xmvc_starttime'), 'START_TIME', '');
        $this->addColumn($this->dic->language()->txt('rep_robj_xmvc_endtime'), 'END_TIME', '');
        $this->addColumn($this->dic->language()->txt('rep_robj_xmvc_vvd_filesize'), 'FILE_SIZE', '');
        $this->addColumn('', 'DOWNLOAD', '');

        $this->setFormAction($this->dic->ctrl()->getFormAction($this->parent_obj, 'showContent'));
        $this->setEnableHeader(true);

        $this->setExternalSorting(false);
        $this->setExternalSegmentation(false);
        $this->setShowRowsSelector(false);

        $this->setDefaultOrderField('START_TIME');
        $this->setDefaultOrderDirection('asc');
        $this->enable('sort');

        $this->setRowTemplate('tpl.recordings_vvd_table_row.html', 'Customizing/global/plugins/Services/Repository/RepositoryObject/MultiVc');
        $this->setEnableNumInfo(false);

        $this->addCommandButton('confirmDeleteRecords', $DIC->language()->txt('delete'));
    }

    private function transformFileSize($bytes) {
        if (!is_numeric($bytes) || !is_finite($bytes)) {
        return "-";
        }

        if ($bytes <= 0) {
            return "0 bytes";
        }

        $units = ["bytes", "kB", "MB", "GB", "TB", "PB"];
        $nr = floor(log($bytes, 1000));

        return number_format($bytes / pow(1000, $nr), 1) . " " . $units[$nr];
    }

    /**
     * Fill a single data row.
     * @throws ilDateTimeException
     */
    protected function fillRow(array $a_set): void
    {
        $a_set['START_TIME'] = new ilDateTime($a_set['START_TIME'], IL_CAL_UNIX);
        $a_set['END_TIME'] = new ilDateTime($a_set['END_TIME'], IL_CAL_UNIX);

        $this->tpl->setVariable('ROWSELECTOR', $a_set['rowSelector']);
        $this->tpl->setVariable('STARTTIME', ilDatePresentation::formatDate($a_set['START_TIME']));
        $this->tpl->setVariable('ENDTIME', ilDatePresentation::formatDate($a_set['END_TIME']));
        $this->tpl->setVariable('FILESIZE', $this->transformFileSize($a_set['FILE_SIZE']));
        $this->tpl->setVariable('DOWNLOAD', $this->buildDownloadUrl($a_set['ROOM_ID'], $a_set['SESSION_ID']));
        $this->tpl->setVariable('DOWNLOAD_TXT', $this->dic->language()->txt('rep_robj_xmvc_vvd_download'));
    }

    private function buildDownloadUrl($roomId, $sessionId) {
        $url = ILIAS_HTTP_PATH . '/' . $this->dic->ctrl()->getLinkTargetByClass(array('ilObjMultiVcGUI'), 'showContent')
        . '&amp;recordingVisavid=1&amp;roomId=' . $roomId . '&amp;sessionId=' . $sessionId;
        return $url;
    }

    public function addRowSelector(array $a_data): array
    {
        foreach ($a_data as $key => $data) {
            $checkbox = new ilCheckboxInputGUI('', 'rec_id[]');
            $checkbox->setValue($key);
            //            $checkbox->setChecked( isset($_POST) && isset($_POST['rec_id']) && array_search($a_data[$key], $_POST['rec_id']) );
            if ($this->dic->http()->wrapper()->post()->has('rec_id')) {
                $recId = $this->dic->http()->wrapper()->post()->retrieve('rec_id', $this->dic->refinery()->kindlyTo()->string());
                $checkbox->setChecked(array_search($data, $recId));
            }
            $a_data[$key]['rowSelector'] = $checkbox->render();
        } // EOF foreach ($a_data as $a_datum)
        return $a_data;
    }


}
