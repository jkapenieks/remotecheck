<?php
namespace assignsubmission_remotecheck\local;

defined('MOODLE_INTERNAL') || die();

class formula_evaluator {
    /**
     * Evaluate a formula string using p1..p9 variables.
     * Allowed tokens: numbers, + - * / ( ) ^, whitespace, and p1..p9
     * Returns float|null
     */
    public static function evaluate(string $formula, array $params): ?float {
        $expr = trim($formula);
        if ($expr === '') { return null; }
        // Normalize power operator ^ to ** for PHP.
        $expr = str_replace('^', '**', $expr);

        // Substitute variables.
        for ($i=1; $i<=9; $i++) {
            $val = isset($params[$i]) ? (float)$params[$i] : 0.0;
            $expr = preg_replace('/\bp' . $i . '\b/i', (string)$val, $expr);
        }

        // Whitelist check after substitution: only digits, operators, dots, parentheses and spaces should remain.
        if (!preg_match('/^[0-9\+\-\*\/\(\)\.\s\*\*]+$/', $expr)) {
            return null; // Unsafe.
        }

        try {
            // Evaluate in a restricted scope.
            $res = null;
            $res = eval('return (float)(' . $expr . ');');
            if ($res === false) { return null; }
            return (float)$res;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
