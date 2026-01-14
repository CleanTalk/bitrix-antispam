<?php
namespace Cleantalk\Integrations;

use CleantalkAntispam;

class Bitrix24Integration {
    public function process($fields) {
        $fields['sender_email'] = $fields['email'] ?? ($fields['sender_email'] ?? '');
        $fields['sender_nickname'] = $fields['name'] ?? ($fields['sender_nickname'] ?? '');
        $fields['type'] = 'feedback_general_contact_form';
        $result = \CleantalkAntispam::CheckAllBefore($fields, false);
        return [
            'apbct' => [
                'blocked' => isset($result['allow']) && $result['allow'] == 0 ? 1 : 0,
                'comment' => $result['ct_result_comment'] ?? ($result['comment'] ?? ''),
            ]
        ];
    }
}
