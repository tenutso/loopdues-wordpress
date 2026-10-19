<?php
/**
 * POST /wp-json/loopdues/v1/webhook: LoopDues tells us when a member changes, so access follows renewals and
 * lapses without the member signing in again. Signed like every LoopDues webhook:
 * LoopDues-Signature: t={unix time},v1=HMAC-SHA256("{t}.{raw body}", secret).
 *
 * Only members who have signed in here at least once have a WordPress user; events for anyone else are ignored.
 */

defined('ABSPATH') || exit;

const LOOPDUES_WEBHOOK_TOLERANCE = 300; // seconds

add_action('rest_api_init', function () {
    register_rest_route('loopdues/v1', '/webhook', [
        'methods' => 'POST',
        'callback' => 'loopdues_handle_webhook',
        'permission_callback' => '__return_true', // authenticated by the signature
    ]);
});

function loopdues_handle_webhook(WP_REST_Request $request)
{
    $c = loopdues_connection();
    if (! $c) {
        return new WP_REST_Response(['ok' => false, 'message' => 'Not connected.'], 503);
    }

    $body = $request->get_body();
    if (! loopdues_webhook_signature_ok((string) $request->get_header('loopdues_signature'), $body, $c['secret'])) {
        return new WP_REST_Response(['ok' => false, 'message' => 'Bad signature.'], 401);
    }

    $event = json_decode($body, true);
    $type = $event['type'] ?? '';
    $member = $event['data']['member'] ?? null;
    if (! is_array($member) || empty($member['id'])) {
        return new WP_REST_Response(['ok' => true, 'message' => 'Nothing to do.'], 200);
    }

    $user = loopdues_user_by_member_id($member['id']);
    if (! $user) {
        return new WP_REST_Response(['ok' => true, 'message' => 'Member has not signed in to this site.'], 200);
    }

    if ($type === 'member.deleted') {
        loopdues_unlink_user($user->ID);

        return new WP_REST_Response(['ok' => true, 'message' => 'Unlinked.'], 200);
    }

    if (strpos($type, 'membership.') === 0 || strpos($type, 'payment.') === 0) {
        $at = strtotime($event['created_at'] ?? '') ?: time();
        $stored = loopdues_store_member($user->ID, $member, $at, ['source' => 'webhook', 'event' => $type, 'event_id' => (string) ($event['id'] ?? '')]);

        return new WP_REST_Response(['ok' => true, 'message' => $stored ? 'Updated.' : 'Older than what we have; skipped.'], 200);
    }

    return new WP_REST_Response(['ok' => true, 'message' => 'Ignored.'], 200);
}

function loopdues_webhook_signature_ok($header, $body, $secret)
{
    if (! preg_match('/t=(\d+),v1=([a-f0-9]{64})/', $header, $m)) {
        return false;
    }
    if (abs(time() - (int) $m[1]) > LOOPDUES_WEBHOOK_TOLERANCE) {
        return false;
    }

    return hash_equals(loopdues_sign($m[1].'.'.$body, $secret), $m[2]);
}
