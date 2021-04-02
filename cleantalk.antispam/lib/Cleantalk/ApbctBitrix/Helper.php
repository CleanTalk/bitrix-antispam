<?php

namespace Cleantalk\ApbctBitrix;

class Helper extends \Cleantalk\Common\Helper {

    /**
     * Get fw stats from the storage.
     *
     * @return array
     * @example array( 'firewall_updating' => false, 'firewall_updating_id' => md5(), 'firewall_update_percent' => 0, 'firewall_updating_last_start' => 0 )
     * @important This method must be overloaded in the CMS-based Helper class.
     */
    public static function getFwStats()
    {
        return array('firewall_updating_id' => \COption::GetOptionInt('cleantalk.antispam','firewall_updating_id', null), 'firewall_updating_last_start' => \COption::GetOptionInt('cleantalk.antispam','firewall_updating_last_start', 0), 'firewall_update_percent' => \COption::GetOptionInt('cleantalk.antispam','firewall_update_percent', 0));
    }

    /**
     * Save fw stats on the storage.
     *
     * @param array $fw_stats
     * @return bool
     * @important This method must be overloaded in the CMS-based Helper class.
     */
    public static function setFwStats( $fw_stats )
    {
        \COption::SetOptionInt('cleantalk.antispam', 'firewall_updating_id', isset($fw_stats['firewall_updating_id']) ? $fw_stats['firewall_updating_id'] : null);
        \COption::SetOptionInt('cleantalk.antispam', 'firewall_updating_last_start', isset($fw_stats['firewall_updating_last_start']) ? $fw_stats['firewall_updating_last_start'] : 0);
        \COption::SetOptionInt('cleantalk.antispam', 'firewall_update_percent', isset($fw_stats['firewall_update_percent']) ? $fw_stats['firewall_update_percent'] : 0);
    }

    /**
     * Implement here any actions after SFW updating finished.
     *
     * @return void
     */
    public static function SfwUpdate_DoFinisnAction()
    {
        \COption::SetOptionInt('cleantalk.antispam', 'sfw_last_update', time());
    }
}