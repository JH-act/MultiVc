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

    public function __construct(object $a_parent_obj, string $a_parent_cmd = '', string $a_template_context = '')
    {
        global $DIC;

        $this->dic = $DIC;

        $this->setId('table_recordings');
        parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);

        $this->addColumn($this->dic->language()->txt('rep_robj_xmvc_select'), '', '5%');
        $this->addColumn($this->dic->language()->txt('rep_robj_xmvc_starttime'), 'BEGIN', '');
        $this->addColumn($this->dic->language()->txt('rep_robj_xmvc_endtime'), 'END', '');
        $this->addColumn($this->dic->language()->txt('rep_robj_xmvc_vvd_filesize'), 'FILE_SIZE', '');
        $this->addColumn('', 'DOWNLOAD', '');

        $this->setFormAction($this->dic->ctrl()->getFormAction($this->parent_obj, 'showContent'));
        $this->setEnableHeader(true);

        $this->setExternalSorting(false);
        $this->setExternalSegmentation(false);
        $this->setShowRowsSelector(false);

        $this->setDefaultOrderField('BEGIN');
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
        $a_set['BEGIN'] = new ilDateTime($a_set['BEGIN'], IL_CAL_UNIX);
        $a_set['END'] = new ilDateTime($a_set['END'], IL_CAL_UNIX);

        $this->tpl->setVariable('ROWSELECTOR', $a_set['rowSelector']);
        $this->tpl->setVariable('BEGIN', ilDatePresentation::formatDate($a_set['BEGIN']));
        $this->tpl->setVariable('END', ilDatePresentation::formatDate($a_set['END']));
        $this->tpl->setVariable('FILESIZE', $this->transformFileSize($a_set['FILE_SIZE']));
        $this->tpl->setVariable('DOWNLOAD', 'herunterladen');

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
