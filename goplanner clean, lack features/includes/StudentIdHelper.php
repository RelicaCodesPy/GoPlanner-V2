<?php
// includes/StudentIdHelper.php

class StudentIdHelper {

    private $db;
    const PATTERN = '/^\d{2}-[1-5]-\d{4}$/';

    public function __construct($db) {
        $this->db = $db;
    }

    public function validate($studentId, $excludeUserId = null) {
        $studentId = trim(strtoupper($studentId));

        if (empty($studentId)) {
            return ['valid' => false, 'message' => 'Student ID is required.'];
        }

        if (!preg_match(self::PATTERN, $studentId)) {
            return [
                'valid' => false,
                'message' => 'Invalid format. Use: YY-Y-NNNN (e.g., 25-1-0001)'
            ];
        }

        $parts = explode('-', $studentId);
        $year = intval($parts[0]);
        $yearLevel = intval($parts[1]);
        $seqNum = intval($parts[2]);
        $currentShortYear = intval(date('y'));

        if ($year < 20 || $year > $currentShortYear + 1) {
            return [
                'valid' => false,
                'message' => "Invalid year. Expected 20-" . ($currentShortYear + 1) . ", got {$year}."
            ];
        }

        if ($yearLevel < 1 || $yearLevel > 5) {
            return ['valid' => false, 'message' => 'Year level must be 1-5.'];
        }

        if ($seqNum < 1) {
            return ['valid' => false, 'message' => 'Sequence number must be at least 0001.'];
        }

        // Check duplicate
        if ($excludeUserId) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE student_id = ? AND id != ?");
            $stmt->execute([$studentId, $excludeUserId]);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE student_id = ?");
            $stmt->execute([$studentId]);
        }

        if ($stmt->fetch()) {
            return ['valid' => false, 'message' => 'This Student ID is already taken.'];
        }

        return ['valid' => true, 'message' => 'Student ID is available!'];
    }

    public function generate($yearLevel = 1) {
        $yy = date('y');
        $yearLevel = max(1, min(5, intval($yearLevel)));
        $pattern = $yy . '-' . $yearLevel . '-%';

        $stmt = $this->db->prepare("
            SELECT student_id FROM users
            WHERE student_id LIKE ?
            ORDER BY CAST(SUBSTRING_INDEX(student_id, '-', -1) AS UNSIGNED) DESC
            LIMIT 1
        ");
        $stmt->execute([$pattern]);
        $last = $stmt->fetchColumn();

        if ($last) {
            $parts = explode('-', $last);
            $nextSeq = intval($parts[2]) + 1;
        } else {
            $nextSeq = 1;
        }

        return $yy . '-' . $yearLevel . '-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public static function parse($studentId) {
        if (!preg_match(self::PATTERN, trim($studentId))) {
            return null;
        }
        $parts = explode('-', trim($studentId));
        return [
            'year_short'  => $parts[0],
            'year_full'   => '20' . $parts[0],
            'year_level'  => intval($parts[1]),
            'seq_number'  => intval($parts[2]),
            'full_id'     => implode('-', $parts),
        ];
    }
}