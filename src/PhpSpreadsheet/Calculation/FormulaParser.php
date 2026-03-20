<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation;

/**
 * PARTLY BASED ON:
 * Copyright (c) 2007 E. W. Bachtal, Inc.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * The software is provided "as is", without warranty of any kind, express or implied, including but not
 * limited to the warranties of merchantability, fitness for a particular purpose and noninfringement. In
 * no event shall the authors or copyright holders be liable for any claim, damages or other liability,
 * whether in an action of contract, tort or otherwise, arising from, out of or in connection with the
 * software or the use or other dealings in the software.
 *
 * The following links are no longer valid.
 * https://ewbi.blogs.com/develops/2007/03/excel_formula_p.html
 * https://ewbi.blogs.com/develops/2004/12/excel_formula_p.html
 *
 * @deprecated 5.5.0 No replacement.
 */
class Formula_Parser
{
    // Character constants
    public const QUOTE_DOUBLE = '"';
    public const QUOTE_SINGLE = '\'';
    public const BRACKET_CLOSE = ']';
    public const BRACKET_OPEN = '[';
    public const BRACE_OPEN = '{';
    public const BRACE_CLOSE = '}';
    public const PAREN_OPEN = '(';
    public const PAREN_CLOSE = ')';
    public const SEMICOLON = ';';
    public const WHITESPACE = ' ';
    public const COMMA = ',';
    public const ERROR_START = '#';
    public const OPERATORS_SN = '+-';
    public const OPERATORS_INFIX = '+-*/^&=><';
    public const OPERATORS_POSTFIX = '%';
    /**
     * Formula.
     */
    private string $formula;
    /**
     * Tokens.
     *
     * @var FormulaToken[]
     */
    private array $tokens = [];
    /**
     * Create a new FormulaParser.
     *
     * @param ?string $formula Formula to parse
     */
    public function __construct(?string $formula = '')
    {
        // Check parameters
        if ($formula === null) {
            throw new Exception('Invalid parameter passed: formula');
        }
        // Initialise values
        $this->formula = trim($formula);
        // Parse!
        $this->parse_to_tokens();
    }
    /**
     * Get Formula.
     */
    public function get_formula(): string
    {
        return $this->formula;
    }
    /**
     * Get Token.
     *
     * @param int $id Token id
     */
    public function get_token(int $id = 0): Formula_Token
    {
        if (isset($this->tokens[$id])) {
            return $this->tokens[$id];
        }
        throw new Exception("Token with id {$id} does not exist.");
    }
    /**
     * Get Token count.
     */
    public function get_token_count(): int
    {
        return count($this->tokens);
    }
    /**
     * Get Tokens.
     *
     * @return FormulaToken[]
     */
    public function get_tokens(): array
    {
        return $this->tokens;
    }
    /**
     * Parse to tokens.
     */
    private function parse_to_tokens(): void
    {
        // No attempt is made to verify formulas; assumes formulas are derived from Excel, where
        // they can only exist if valid; stack overflows/underflows sunk as nulls without exceptions.
        // Check if the formula has a valid starting =
        $formula_length = strlen($this->formula);
        if ($formula_length < 2 || $this->formula[0] != '=') {
            return;
        }
        // Helper variables
        $tokens1 = $tokens2 = $stack = [];
        $in_string = $in_path = $in_range = $in_error = false;
        $next_token = null;
        //$token = $previousToken = null;
        $index = 1;
        $value = '';
        $ERRORS = ['#NULL!', '#DIV/0!', '#VALUE!', '#REF!', '#NAME?', '#NUM!', '#N/A'];
        $COMPARATORS_MULTI = ['>=', '<=', '<>'];
        while ($index < $formula_length) {
            // state-dependent character evaluation (order is important)
            // double-quoted strings
            // embeds are doubled
            // end marks token
            if ($in_string) {
                if ($this->formula[$index] == self::QUOTE_DOUBLE) {
                    if ($index + 2 <= $formula_length && $this->formula[$index + 1] == self::QUOTE_DOUBLE) {
                        $value .= self::QUOTE_DOUBLE;
                        ++$index;
                    } else {
                        $in_string = false;
                        $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND, Formula_Token::TOKEN_SUBTYPE_TEXT);
                        $value = '';
                    }
                } else {
                    $value .= $this->formula[$index];
                }
                ++$index;
                continue;
            }
            // single-quoted strings (links)
            // embeds are double
            // end does not mark a token
            if ($in_path) {
                if ($this->formula[$index] == self::QUOTE_SINGLE) {
                    if ($index + 2 <= $formula_length && $this->formula[$index + 1] == self::QUOTE_SINGLE) {
                        $value .= self::QUOTE_SINGLE;
                        ++$index;
                    } else {
                        $in_path = false;
                    }
                } else {
                    $value .= $this->formula[$index];
                }
                ++$index;
                continue;
            }
            // bracked strings (R1C1 range index or linked workbook name)
            // no embeds (changed to "()" by Excel)
            // end does not mark a token
            if ($in_range) {
                if ($this->formula[$index] == self::BRACKET_CLOSE) {
                    $in_range = false;
                }
                $value .= $this->formula[$index];
                ++$index;
                continue;
            }
            // error values
            // end marks a token, determined from absolute list of values
            if ($in_error) {
                $value .= $this->formula[$index];
                ++$index;
                if (in_array($value, $ERRORS)) {
                    $in_error = false;
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND, Formula_Token::TOKEN_SUBTYPE_ERROR);
                    $value = '';
                }
                continue;
            }
            // scientific notation check
            if (str_contains(self::OPERATORS_SN, $this->formula[$index])) {
                if (strlen($value) > 1) {
                    if (preg_match('/^[1-9]{1}(\.\d+)?E{1}$/', $this->formula[$index]) != 0) {
                        $value .= $this->formula[$index];
                        ++$index;
                        continue;
                    }
                }
            }
            // independent character evaluation (order not important)
            // establish state-dependent character evaluations
            if ($this->formula[$index] == self::QUOTE_DOUBLE) {
                if ($value !== '') {
                    // unexpected
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_UNKNOWN);
                    $value = '';
                }
                $in_string = true;
                ++$index;
                continue;
            }
            if ($this->formula[$index] == self::QUOTE_SINGLE) {
                if ($value !== '') {
                    // unexpected
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_UNKNOWN);
                    $value = '';
                }
                $in_path = true;
                ++$index;
                continue;
            }
            if ($this->formula[$index] == self::BRACKET_OPEN) {
                $in_range = true;
                $value .= self::BRACKET_OPEN;
                ++$index;
                continue;
            }
            if ($this->formula[$index] == self::ERROR_START) {
                if ($value !== '') {
                    // unexpected
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_UNKNOWN);
                    $value = '';
                }
                $in_error = true;
                $value .= self::ERROR_START;
                ++$index;
                continue;
            }
            // mark start and end of arrays and array rows
            if ($this->formula[$index] == self::BRACE_OPEN) {
                if ($value !== '') {
                    // unexpected
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_UNKNOWN);
                    $value = '';
                }
                $tmp = new Formula_Token('ARRAY', Formula_Token::TOKEN_TYPE_FUNCTION, Formula_Token::TOKEN_SUBTYPE_START);
                $tokens1[] = $tmp;
                $stack[] = clone $tmp;
                $tmp = new Formula_Token('ARRAYROW', Formula_Token::TOKEN_TYPE_FUNCTION, Formula_Token::TOKEN_SUBTYPE_START);
                $tokens1[] = $tmp;
                $stack[] = clone $tmp;
                ++$index;
                continue;
            }
            if ($this->formula[$index] == self::SEMICOLON) {
                if ($value !== '') {
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND);
                    $value = '';
                }
                /** @var FormulaToken $tmp */
                $tmp = array_pop($stack);
                $tmp->set_value('');
                $tmp->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_STOP);
                $tokens1[] = $tmp;
                $tmp = new Formula_Token(',', Formula_Token::TOKEN_TYPE_ARGUMENT);
                $tokens1[] = $tmp;
                $tmp = new Formula_Token('ARRAYROW', Formula_Token::TOKEN_TYPE_FUNCTION, Formula_Token::TOKEN_SUBTYPE_START);
                $tokens1[] = $tmp;
                $stack[] = clone $tmp;
                ++$index;
                continue;
            }
            if ($this->formula[$index] == self::BRACE_CLOSE) {
                if ($value !== '') {
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND);
                    $value = '';
                }
                /** @var FormulaToken $tmp */
                $tmp = array_pop($stack);
                $tmp->set_value('');
                $tmp->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_STOP);
                $tokens1[] = $tmp;
                /** @var FormulaToken $tmp */
                $tmp = array_pop($stack);
                $tmp->set_value('');
                $tmp->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_STOP);
                $tokens1[] = $tmp;
                ++$index;
                continue;
            }
            // trim white-space
            if ($this->formula[$index] == self::WHITESPACE) {
                if ($value !== '') {
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND);
                    $value = '';
                }
                $tokens1[] = new Formula_Token('', Formula_Token::TOKEN_TYPE_WHITESPACE);
                ++$index;
                while ($this->formula[$index] == self::WHITESPACE && $index < $formula_length) {
                    ++$index;
                }
                continue;
            }
            // multi-character comparators
            if ($index + 2 <= $formula_length) {
                if (in_array(substr($this->formula, $index, 2), $COMPARATORS_MULTI)) {
                    if ($value !== '') {
                        $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND);
                        $value = '';
                    }
                    $tokens1[] = new Formula_Token(substr($this->formula, $index, 2), Formula_Token::TOKEN_TYPE_OPERATORINFIX, Formula_Token::TOKEN_SUBTYPE_LOGICAL);
                    $index += 2;
                    continue;
                }
            }
            // standard infix operators
            if (str_contains(self::OPERATORS_INFIX, $this->formula[$index])) {
                if ($value !== '') {
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND);
                    $value = '';
                }
                $tokens1[] = new Formula_Token($this->formula[$index], Formula_Token::TOKEN_TYPE_OPERATORINFIX);
                ++$index;
                continue;
            }
            // standard postfix operators (only one)
            if (str_contains(self::OPERATORS_POSTFIX, $this->formula[$index])) {
                if ($value !== '') {
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND);
                    $value = '';
                }
                $tokens1[] = new Formula_Token($this->formula[$index], Formula_Token::TOKEN_TYPE_OPERATORPOSTFIX);
                ++$index;
                continue;
            }
            // start subexpression or function
            if ($this->formula[$index] == self::PAREN_OPEN) {
                if ($value !== '') {
                    $tmp = new Formula_Token($value, Formula_Token::TOKEN_TYPE_FUNCTION, Formula_Token::TOKEN_SUBTYPE_START);
                    $tokens1[] = $tmp;
                    $stack[] = clone $tmp;
                    $value = '';
                } else {
                    $tmp = new Formula_Token('', Formula_Token::TOKEN_TYPE_SUBEXPRESSION, Formula_Token::TOKEN_SUBTYPE_START);
                    $tokens1[] = $tmp;
                    $stack[] = clone $tmp;
                }
                ++$index;
                continue;
            }
            // function, subexpression, or array parameters, or operand unions
            if ($this->formula[$index] == self::COMMA) {
                if ($value !== '') {
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND);
                    $value = '';
                }
                /** @var FormulaToken $tmp */
                $tmp = array_pop($stack);
                $tmp->set_value('');
                $tmp->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_STOP);
                $stack[] = $tmp;
                if ($tmp->get_token_type() == Formula_Token::TOKEN_TYPE_FUNCTION) {
                    $tokens1[] = new Formula_Token(',', Formula_Token::TOKEN_TYPE_OPERATORINFIX, Formula_Token::TOKEN_SUBTYPE_UNION);
                } else {
                    $tokens1[] = new Formula_Token(',', Formula_Token::TOKEN_TYPE_ARGUMENT);
                }
                ++$index;
                continue;
            }
            // stop subexpression
            if ($this->formula[$index] == self::PAREN_CLOSE) {
                if ($value !== '') {
                    $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND);
                    $value = '';
                }
                /** @var FormulaToken $tmp */
                $tmp = array_pop($stack);
                $tmp->set_value('');
                $tmp->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_STOP);
                $tokens1[] = $tmp;
                ++$index;
                continue;
            }
            // token accumulation
            $value .= $this->formula[$index];
            ++$index;
        }
        // dump remaining accumulation
        if ($value !== '') {
            $tokens1[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERAND);
        }
        // move tokenList to new set, excluding unnecessary white-space tokens and converting necessary ones to intersections
        $token_count = count($tokens1);
        for ($i = 0; $i < $token_count; ++$i) {
            $token = $tokens1[$i];
            $previous_token = $tokens1[$i - 1] ?? null;
            $next_token = $tokens1[$i + 1] ?? null;
            if ($token->get_token_type() != Formula_Token::TOKEN_TYPE_WHITESPACE) {
                $tokens2[] = $token;
                continue;
            }
            if ($previous_token === null) {
                continue;
            }
            if (!($previous_token->get_token_type() == Formula_Token::TOKEN_TYPE_FUNCTION && $previous_token->get_token_sub_type() == Formula_Token::TOKEN_SUBTYPE_STOP || $previous_token->get_token_type() == Formula_Token::TOKEN_TYPE_SUBEXPRESSION && $previous_token->get_token_sub_type() == Formula_Token::TOKEN_SUBTYPE_STOP || $previous_token->get_token_type() == Formula_Token::TOKEN_TYPE_OPERAND)) {
                continue;
            }
            if ($next_token === null) {
                continue;
            }
            if (!($next_token->get_token_type() == Formula_Token::TOKEN_TYPE_FUNCTION && $next_token->get_token_sub_type() == Formula_Token::TOKEN_SUBTYPE_START || $next_token->get_token_type() == Formula_Token::TOKEN_TYPE_SUBEXPRESSION && $next_token->get_token_sub_type() == Formula_Token::TOKEN_SUBTYPE_START || $next_token->get_token_type() == Formula_Token::TOKEN_TYPE_OPERAND)) {
                continue;
            }
            $tokens2[] = new Formula_Token($value, Formula_Token::TOKEN_TYPE_OPERATORINFIX, Formula_Token::TOKEN_SUBTYPE_INTERSECTION);
        }
        // move tokens to final list, switching infix "-" operators to prefix when appropriate, switching infix "+" operators
        // to noop when appropriate, identifying operand and infix-operator subtypes, and pulling "@" from function names
        $this->tokens = [];
        $token_count = count($tokens2);
        for ($i = 0; $i < $token_count; ++$i) {
            $token = $tokens2[$i];
            $previous_token = $tokens2[$i - 1] ?? null;
            if ($token->get_token_type() == Formula_Token::TOKEN_TYPE_OPERATORINFIX && $token->get_value() == '-') {
                if ($i == 0) {
                    $token->set_token_type(Formula_Token::TOKEN_TYPE_OPERATORPREFIX);
                } elseif ($previous_token?->get_token_type() == Formula_Token::TOKEN_TYPE_FUNCTION && $previous_token?->get_token_sub_type() == Formula_Token::TOKEN_SUBTYPE_STOP || $previous_token?->get_token_type() == Formula_Token::TOKEN_TYPE_SUBEXPRESSION && $previous_token?->get_token_sub_type() == Formula_Token::TOKEN_SUBTYPE_STOP || $previous_token?->get_token_type() == Formula_Token::TOKEN_TYPE_OPERATORPOSTFIX || $previous_token?->get_token_type() == Formula_Token::TOKEN_TYPE_OPERAND) {
                    $token->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_MATH);
                } else {
                    $token->set_token_type(Formula_Token::TOKEN_TYPE_OPERATORPREFIX);
                }
                $this->tokens[] = $token;
                continue;
            }
            if ($token->get_token_type() == Formula_Token::TOKEN_TYPE_OPERATORINFIX && $token->get_value() == '+') {
                if ($i == 0) {
                    continue;
                }
                if ($previous_token?->get_token_type() == Formula_Token::TOKEN_TYPE_FUNCTION && $previous_token?->get_token_sub_type() == Formula_Token::TOKEN_SUBTYPE_STOP || $previous_token?->get_token_type() == Formula_Token::TOKEN_TYPE_SUBEXPRESSION && $previous_token?->get_token_sub_type() == Formula_Token::TOKEN_SUBTYPE_STOP || $previous_token?->get_token_type() == Formula_Token::TOKEN_TYPE_OPERATORPOSTFIX || $previous_token?->get_token_type() == Formula_Token::TOKEN_TYPE_OPERAND) {
                    $token->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_MATH);
                } else {
                    continue;
                }
                $this->tokens[] = $token;
                continue;
            }
            if ($token->get_token_type() == Formula_Token::TOKEN_TYPE_OPERATORINFIX && $token->get_token_sub_type() == Formula_Token::TOKEN_SUBTYPE_NOTHING) {
                if (str_contains('<>=', substr($token->get_value(), 0, 1))) {
                    $token->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_LOGICAL);
                } elseif ($token->get_value() == '&') {
                    $token->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_CONCATENATION);
                } else {
                    $token->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_MATH);
                }
                $this->tokens[] = $token;
                continue;
            }
            if ($token->get_token_type() == Formula_Token::TOKEN_TYPE_OPERAND && $token->get_token_sub_type() == Formula_Token::TOKEN_SUBTYPE_NOTHING) {
                if (!is_numeric($token->get_value())) {
                    if (strtoupper($token->get_value()) == 'TRUE' || strtoupper($token->get_value()) == 'FALSE') {
                        $token->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_LOGICAL);
                    } else {
                        $token->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_RANGE);
                    }
                } else {
                    $token->set_token_sub_type(Formula_Token::TOKEN_SUBTYPE_NUMBER);
                }
                $this->tokens[] = $token;
                continue;
            }
            if ($token->get_token_type() == Formula_Token::TOKEN_TYPE_FUNCTION) {
                if ($token->get_value() !== '') {
                    if (str_starts_with($token->get_value(), '@')) {
                        $token->set_value(substr($token->get_value(), 1));
                    }
                }
            }
            $this->tokens[] = $token;
        }
    }
}