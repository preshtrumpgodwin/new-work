<?php
/**
 * GradingEngine — converts raw scores to grades, remarks, GPA, positions.
 * All grade boundaries are configurable per school via grading_json in school_settings.
 * Falls back to Nigerian standard WAEC-style if no config found.
 */
class GradingEngine {

    private array $scale;

    public function __construct(array $custom_scale = []) {
        // Default: Nigerian 100-point scale (WAEC-aligned)
        $this->scale = $custom_scale ?: [
            ['min' => 75, 'max' => 100, 'grade' => 'A1', 'remark' => 'Distinction',    'points' => 4.0],
            ['min' => 70, 'max' => 74,  'grade' => 'B2', 'remark' => 'Excellent',      'points' => 3.7],
            ['min' => 65, 'max' => 69,  'grade' => 'B3', 'remark' => 'Very Good',      'points' => 3.3],
            ['min' => 60, 'max' => 64,  'grade' => 'C4', 'remark' => 'Credit',         'points' => 3.0],
            ['min' => 55, 'max' => 59,  'grade' => 'C5', 'remark' => 'Credit',         'points' => 2.7],
            ['min' => 50, 'max' => 54,  'grade' => 'C6', 'remark' => 'Credit',         'points' => 2.3],
            ['min' => 45, 'max' => 49,  'grade' => 'D7', 'remark' => 'Pass',           'points' => 2.0],
            ['min' => 40, 'max' => 44,  'grade' => 'E8', 'remark' => 'Pass',           'points' => 1.0],
            ['min' => 0,  'max' => 39,  'grade' => 'F9', 'remark' => 'Fail',           'points' => 0.0],
        ];
    }

    public function grade(float $score): array {
        foreach ($this->scale as $band) {
            if ($score >= $band['min'] && $score <= $band['max']) {
                return $band;
            }
        }
        return ['grade' => 'F9', 'remark' => 'Fail', 'points' => 0.0, 'min' => 0, 'max' => 39];
    }

    public function gradeLabel(float $score): string {
        return $this->grade($score)['grade'];
    }

    public function remark(float $score): string {
        return $this->grade($score)['remark'];
    }

    /** Compute GPA from an array of total scores */
    public function gpa(array $scores): float {
        if (empty($scores)) return 0.0;
        $total = array_sum(array_map(fn($s) => $this->grade((float)$s)['points'], $scores));
        return round($total / count($scores), 2);
    }

    /** Assign positions from an assoc array of [student_uuid => total] */
    public function positions(array $totals): array {
        arsort($totals);
        $positions = [];
        $rank = 1;
        $prev_total = null;
        $count = 0;
        foreach ($totals as $uuid => $total) {
            if ($total !== $prev_total) {
                $rank = $count + 1;
            }
            $positions[$uuid] = $rank;
            $prev_total = $total;
            $count++;
        }
        return $positions;
    }

    /** Ordinal suffix: 1→1st, 2→2nd, 3→3rd, 4→4th */
    public static function ordinal(int $n): string {
        $s = ['th','st','nd','rd'];
        $v = $n % 100;
        return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }

    public function scale(): array {
        return $this->scale;
    }

    /** Load from DB or return default instance */
    public static function fromDB(PDO $pdo, string $school_uuid): self {
        try {
            $st = $pdo->prepare("SELECT grading_json FROM school_settings WHERE school_uuid=? LIMIT 1");
            $st->execute([$school_uuid]);
            $row = $st->fetchColumn();
            if ($row) {
                $parsed = json_decode($row, true);
                if (is_array($parsed) && !empty($parsed)) return new self($parsed);
            }
        } catch (Exception $e) {}
        return new self();
    }
}
