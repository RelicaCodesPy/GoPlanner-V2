<?php
// includes/MailHelper.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    // Composer autoload
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/PHPMailer/src/Exception.php')) {
    // Manual installation
    require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
}

require_once __DIR__ . '/../config/mail.php';

class MailHelper {

    /**
     * Create a configured PHPMailer instance
     */
    private static function createMailer() {
        $mail = new PHPMailer(true);

        if (!MAIL_ENABLED) {
            return $mail;
        }

        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_SMTP_SECURE;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // Sender
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        return $mail;
    }

    /**
     * Generate Google Calendar "Add Event" URL
     */
    public static function getGoogleCalendarUrl($title, $date, $time = null, $location = '', $description = '') {
        $title = urlencode($title);

        if ($time) {
            $startDateTime = date('Ymd\THis', strtotime($date . ' ' . $time));
            $endDateTime = date('Ymd\THis', strtotime($date . ' ' . $time . ' +1 hour'));
        } else {
            $startDateTime = date('Ymd', strtotime($date));
            $endDateTime = date('Ymd', strtotime($date . ' +1 day'));
        }

        $location = urlencode($location);
        $description = urlencode($description);

        return "https://calendar.google.com/calendar/render?action=TEMPLATE"
            . "&text=" . $title
            . "&dates=" . $startDateTime . "/" . $endDateTime
            . "&location=" . $location
            . "&details=" . $description
            . "&sf=true&output=xml";
    }

    /**
     * Generate ICS content for a single event
     */
    public static function generateICS($title, $date, $time = null, $location = '', $description = '', $eventId = 0) {
        $dateStr = date('Ymd', strtotime($date));
        $uid = 'goplanner-' . $eventId . '-' . $dateStr . '@debesmscat.edu.ph';

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//GoPlanner//DEBESMSCAT//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:" . CALENDAR_NAME . "\r\n";
        $ics .= "X-WR-TIMEZONE:" . CALENDAR_TIMEZONE . "\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . $uid . "\r\n";
        $ics .= "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";

        if ($time) {
            $ics .= "DTSTART:" . $dateStr . "T" . str_replace(':', '', $time) . "00\r\n";
            $ics .= "DTEND:" . $dateStr . "T" . str_replace(':', '', $time) . "00\r\n";
        } else {
            $ics .= "DTSTART;VALUE=DATE:" . $dateStr . "\r\n";
            $nextDay = date('Ymd', strtotime($date . ' +1 day'));
            $ics .= "DTEND;VALUE=DATE:" . $nextDay . "\r\n";
        }

        $ics .= "SUMMARY:" . str_replace(["\r", "\n"], "", $title) . "\r\n";
        if ($location) $ics .= "LOCATION:" . str_replace(["\r", "\n"], "", $location) . "\r\n";
        if ($description) $ics .= "DESCRIPTION:" . str_replace(["\r", "\n"], "", $description) . "\r\n";
        $ics .= "URL:" . APP_URL . "/pages/announcements/view.php?id=" . $eventId . "\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "BEGIN:VALARM\r\n";
        $ics .= "TRIGGER:-PT30M\r\n";
        $ics .= "ACTION:DISPLAY\r\n";
        $ics .= "DESCRIPTION:Reminder\r\n";
        $ics .= "END:VALARM\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Build email HTML template for event announcement
     */
    public static function buildEventEmailHtml($announcement, $recipientName, $gcalUrl) {
        $title = htmlspecialchars($announcement['title']);
        $body = $announcement['body'] ?? '';
        $eventDate = $announcement['event_date'];
        $eventTime = $announcement['event_time'] ?? null;
        $location = $announcement['map_address'] ?? '';
        $collegeName = $announcement['college_name'] ?? 'DEBESMSCAT';
        $collegeColor = $announcement['college_color'] ?? '#3b82f6';
        $viewUrl = APP_URL . '/pages/announcements/view.php?id=' . $announcement['id'];

        $formattedDate = date('l, F j, Y', strtotime($eventDate));
        $formattedTime = $eventTime ? date('g:i A', strtotime($eventTime)) : null;

        // Truncate body for email
        if (strlen($body) > 300) {
            $body = substr($body, 0, 300) . '...';
        }

        $html = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#0f172a;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

    <!-- Header -->
    <tr><td style="background:linear-gradient(135deg, ' . $collegeColor . ', ' . $collegeColor . 'cc);padding:28px 32px;border-radius:12px 12px 0 0;">
        <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="color:white;font-size:22px;font-weight:bold;">📅 New Event Announcement</td>
            <td align="right" style="color:rgba(255,255,255,0.8);font-size:13px;">' . htmlspecialchars($collegeName) . '</td>
        </tr>
        </table>
    </td></tr>

    <!-- Body -->
    <tr><td style="background:#1e293b;padding:28px 32px;">
        <p style="color:#94a3b8;font-size:14px;margin:0 0 8px;">Hi ' . htmlspecialchars($recipientName) . ',</p>
        <p style="color:#94a3b8;font-size:14px;margin:0 0 24px;">A new event has been scheduled. Here are the details:</p>

        <!-- Event Card -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a;border:1px solid #334155;border-radius:10px;margin-bottom:24px;">
        <tr><td style="padding:24px;">
            <h2 style="color:#f1f5f9;font-size:20px;font-weight:bold;margin:0 0 16px;">' . $title . '</h2>

            <!-- Date -->
            <table cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
            <tr>
                <td style="padding-right:12px;vertical-align:top;">
                    <div style="width:44px;height:44px;background:' . $collegeColor . ';border-radius:10px;text-align:center;line-height:44px;color:white;font-size:20px;font-weight:bold;">' . date('d', strtotime($eventDate)) . '</div>
                </td>
                <td style="vertical-align:top;">
                    <div style="color:#f1f5f9;font-size:15px;font-weight:600;">' . $formattedDate . '</div>' .
                    ($formattedTime ? '<div style="color:#64748b;font-size:13px;margin-top:2px;">🕐 ' . $formattedTime . '</div>' : '') . '
                </td>
            </tr>
            </table>';

        // Location
        if ($location) {
            $html .= '
            <table cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
            <tr>
                <td style="padding-right:8px;color:#64748b;font-size:14px;">📍</td>
                <td style="color:#94a3b8;font-size:14px;">' . htmlspecialchars($location) . '</td>
            </tr>
            </table>';
        }

        // Description
        if ($body) {
            $html .= '
            <div style="color:#94a3b8;font-size:14px;line-height:1.6;margin-top:16px;padding-top:16px;border-top:1px solid #334155;">'
                . nl2br(htmlspecialchars($body)) . '</div>';
        }

        $html .= '
        </td></tr>
        </table>

        <!-- Action Buttons -->
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
        <tr>
            <td style="padding-right:8px;">
                <a href="' . $gcalUrl . '" target="_blank" style="display:block;background:#4285f4;color:white;text-decoration:none;padding:14px 20px;border-radius:8px;font-size:14px;font-weight:bold;text-align:center;">
                    📅 Add to Google Calendar
                </a>
            </td>
            <td style="padding-left:8px;">
                <a href="' . $viewUrl . '" style="display:block;background:#3b82f6;color:white;text-decoration:none;padding:14px 20px;border-radius:8px;font-size:14px;font-weight:bold;text-align:center;">
                    👁️ View Details
                </a>
            </td>
        </tr>
        </table>

        <p style="color:#475569;font-size:12px;margin:0;text-align:center;">
            📎 An .ics calendar file is attached — open it to add this event to any calendar app.
        </p>
    </td></tr>

    <!-- Footer -->
    <tr><td style="background:#1e293b;padding:16px 32px;border-top:1px solid #334155;border-radius:0 0 12px 12px;">
        <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="color:#475569;font-size:12px;">
                GoPlanner DEBESMSCAT · This is an automated notification
            </td>
            <td align="right">
                <a href="' . APP_URL . '/pages/settings/profile.php" style="color:#3b82f6;font-size:12px;text-decoration:none;">Notification Settings</a>
            </td>
        </tr>
        </table>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>';

        return $html;
    }

    /**
     * Send event notification email to a single recipient
     */
    public static function sendEventNotification($recipientEmail, $recipientName, $announcement) {
        if (!MAIL_ENABLED) {
            error_log('Mail disabled — skipping email to ' . $recipientEmail);
            return false;
        }

        try {
            $mail = self::createMailer();

            // Recipient
            $mail->addAddress($recipientEmail, $recipientName);

            // Generate Google Calendar URL
            $gcalUrl = self::getGoogleCalendarUrl(
                $announcement['title'],
                $announcement['event_date'],
                $announcement['event_time'] ?? null,
                $announcement['map_address'] ?? '',
                'Event from GoPlanner: ' . APP_URL . '/pages/announcements/view.php?id=' . $announcement['id']
            );

            // Email content
            $mail->isHTML(true);
            $mail->Subject = '📅 ' . $announcement['title'] . ' — ' . date('M j, Y', strtotime($announcement['event_date']));
            $mail->Body    = self::buildEventEmailHtml($announcement, $recipientName, $gcalUrl);
            $mail->AltBody = "New Event: " . $announcement['title'] . "\n"
                . "Date: " . date('l, F j, Y', strtotime($announcement['event_date'])) . "\n"
                . (!empty($announcement['event_time']) ? "Time: " . date('g:i A', strtotime($announcement['event_time'])) . "\n" : "")
                . (!empty($announcement['map_address']) ? "Location: " . $announcement['map_address'] . "\n" : "")
                . "\nView: " . APP_URL . "/pages/announcements/view.php?id=" . $announcement['id']
                . "\nAdd to Google Calendar: " . $gcalUrl;

            // Attach .ics file
            $icsContent = self::generateICS(
                $announcement['title'],
                $announcement['event_date'],
                $announcement['event_time'] ?? null,
                $announcement['map_address'] ?? '',
                $announcement['body'] ?? '',
                $announcement['id']
            );

            $icsFilename = 'event-' . preg_replace('/[^a-zA-Z0-9]/', '-', strtolower($announcement['title'])) . '.ics';
            $mail->addStringAttachment($icsContent, $icsFilename, 'base64', 'text/calendar; method=PUBLISH');

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log('Mail error for ' . $recipientEmail . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
 * Send event notification to all target users (with rate limiting + queue)
 */
public static function sendEventNotifications($db, $announcement) {
    if (!MAIL_ENABLED) return ['sent' => 0, 'failed' => 0, 'queued' => 0, 'skipped' => 0];

    $sent = 0;
    $failed = 0;
    $queued = 0;
    $skipped = 0;

    // Get target users
    $users = self::getTargetUsers($db, $announcement);
    $totalUsers = count($users);

    error_log("GoPlanner Mail: Event '{$announcement['title']}' → $totalUsers target user(s)");

    if ($totalUsers === 0) {
        return ['sent' => 0, 'failed' => 0, 'queued' => 0, 'skipped' => 0];
    }

    // Check how many emails sent today
    $todayCount = self::getEmailsSentToday($db);
    $dailyLimit = defined('MAIL_DAILY_LIMIT') ? MAIL_DAILY_LIMIT : 450;
    $remaining = max(0, $dailyLimit - $todayCount);

    if ($remaining <= 0) {
        // Daily limit reached — queue all for tomorrow
        error_log("GoPlanner Mail: Daily limit ($dailyLimit) reached. Queueing all $totalUsers emails.");
        foreach ($users as $u) {
            self::queueEmail($db, $u['email'], $u['first_name'], $u['last_name'], $announcement);
            $queued++;
        }
        return ['sent' => 0, 'failed' => 0, 'queued' => $queued, 'skipped' => 0, 'daily_limit' => true];
    }

    // Send up to remaining limit, queue the rest
    $batchDelay = defined('MAIL_BATCH_DELAY') ? MAIL_BATCH_DELAY : 1000000;
    $canSendNow = min($totalUsers, $remaining);

    $counter = 0;
    foreach ($users as $u) {
        $fullName = trim($u['first_name'] . ' ' . $u['last_name']);

        if (empty($u['email'])) {
            $failed++;
            continue;
        }

        $counter++;

        if ($counter > $canSendNow) {
            // Over daily limit — queue remaining
            self::queueEmail($db, $u['email'], $u['first_name'], $u['last_name'], $announcement);
            $queued++;
            continue;
        }

        $result = self::sendEventNotification($u['email'], $fullName, $announcement);

        if ($result) {
            $sent++;
            self::logEmailSent($db, $u['email'], $announcement['id']);
        } else {
            $failed++;
        }

        // Rate limiting delay
        if ($counter < $canSendNow) {
            usleep($batchDelay);
        }
    }

    $msg = "GoPlanner Mail: Done — $sent sent, $failed failed, $queued queued";
    if ($queued > 0) $msg .= " (daily limit: $dailyLimit, used: " . ($todayCount + $sent) . "/$dailyLimit)";
    error_log($msg);

    return [
        'sent' => $sent,
        'failed' => $failed,
        'queued' => $queued,
        'skipped' => $skipped,
        'daily_limit' => $dailyLimit,
        'remaining_after' => max(0, $remaining - $sent)
    ];
}

/**
 * Get how many emails were sent today
 */
private static function getEmailsSentToday($db) {
    try {
        // Check if log table exists
        $check = $db->prepare("SHOW TABLES LIKE 'email_log'");
        $check->execute();
        if (!$check->fetch()) return 0;

        $stmt = $db->prepare("SELECT COUNT(*) FROM email_log WHERE sent_date = CURDATE()");
        $stmt->execute();
        return intval($stmt->fetchColumn());
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Log that an email was sent
 */
private static function logEmailSent($db, $email, $announcementId) {
    try {
        $db->prepare("INSERT INTO email_log (email, announcement_id, sent_date, sent_at) VALUES (?, ?, CURDATE(), NOW())")
           ->execute([$email, $announcementId]);
    } catch (Exception $e) {
        // Table might not exist — that's ok
    }
}

/**
 * Queue an email for later delivery
 */
private static function queueEmail($db, $email, $firstName, $lastName, $announcement) {
    try {
        $db->prepare("INSERT INTO email_queue (email, first_name, last_name, announcement_id, event_date, event_time, map_address, title, body, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')")
           ->execute([
               $email,
               $firstName,
               $lastName,
               $announcement['id'],
               $announcement['event_date'],
               $announcement['event_time'] ?? null,
               $announcement['map_address'] ?? null,
               $announcement['title'],
               $announcement['body'] ?? ''
           ]);
    } catch (Exception $e) {
        error_log('Queue email error: ' . $e->getMessage());
    }
}

/**
 * Process email queue (call via cron job daily)
 */
public static function processQueue($db) {
    if (!MAIL_ENABLED) return ['sent' => 0, 'failed' => 0];

    $dailyLimit = defined('MAIL_DAILY_LIMIT') ? MAIL_DAILY_LIMIT : 450;
    $todayCount = self::getEmailsSentToday($db);
    $remaining = max(0, $dailyLimit - $todayCount);

    if ($remaining <= 0) {
        return ['sent' => 0, 'failed' => 0, 'reason' => 'daily_limit'];
    }

    // Get pending queued emails
    $stmt = $db->prepare("
        SELECT * FROM email_queue
        WHERE status = 'pending'
        ORDER BY created_at ASC
        LIMIT ?
    ");
    $stmt->execute([$remaining]);
    $queued = $stmt->fetchAll();

    $sent = 0;
    $failed = 0;
    $batchDelay = defined('MAIL_BATCH_DELAY') ? MAIL_BATCH_DELAY : 1000000;

    foreach ($queued as $q) {
        $fullName = trim($q['first_name'] . ' ' . $q['last_name']);

        $announcement = [
            'id' => $q['announcement_id'],
            'title' => $q['title'],
            'body' => $q['body'],
            'event_date' => $q['event_date'],
            'event_time' => $q['event_time'],
            'map_address' => $q['map_address']
        ];

        $result = self::sendEventNotification($q['email'], $fullName, $announcement);

        if ($result) {
            $sent++;
            self::logEmailSent($db, $q['email'], $q['announcement_id']);
            // Mark as sent
            $db->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")
               ->execute([$q['id']]);
        } else {
            $failed++;
            // Mark as failed (retry later)
            $db->prepare("UPDATE email_queue SET status = 'failed', attempts = attempts + 1, last_attempt = NOW() WHERE id = ?")
               ->execute([$q['id']]);
        }

        usleep($batchDelay);
    }

    return ['sent' => $sent, 'failed' => $failed, 'processed' => count($queued)];
}