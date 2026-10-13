<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validação declarativa: ['email' => 'required|email|max:160|unique:users,email,5'].
 * Mensagens em português, com o rótulo amigável do campo.
 */
final class Validator
{
    private array $errors = [];
    private array $validated = [];

    public function __construct(private array $data, private array $rules, private array $labels = [])
    {
        $this->run();
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $isEmpty = $value === null || $value === '' || $value === [];
            $numeric = in_array('integer', $rules, true) || in_array('numeric', $rules, true);

            if ($isEmpty) {
                if (in_array('required', $rules, true)) {
                    $this->add($field, 'Preencha :campo.');
                } elseif (in_array('boolean', $rules, true)) {
                    $this->validated[$field] = 0;
                } else {
                    $this->validated[$field] = null;
                }
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->check($name, $param, $value, $field, $numeric);
                if ($error !== null) {
                    $this->add($field, $error);
                    break;
                }
            }
            if (!isset($this->errors[$field])) {
                $this->validated[$field] = match (true) {
                    in_array('integer', $rules, true) => (int) $value,
                    in_array('boolean', $rules, true) => in_array($value, [1, '1', 'on', 'true', true], true) ? 1 : 0,
                    in_array('email', $rules, true) => mb_strtolower((string) $value),
                    default => $value,
                };
            }
        }
    }

    private function check(string $rule, ?string $param, mixed $value, string $field, bool $numeric): ?string
    {
        $str = is_scalar($value) ? (string) $value : '';
        switch ($rule) {
            case 'required':
            case 'nullable':
            case 'string':
                return null;
            case 'email':
                return filter_var($str, FILTER_VALIDATE_EMAIL) ? null : 'Informe um e-mail válido.';
            case 'integer':
                return filter_var($str, FILTER_VALIDATE_INT) !== false ? null : ':Campo deve ser um número inteiro.';
            case 'numeric':
                return is_numeric($str) ? null : ':Campo deve ser um número.';
            case 'boolean':
                return null;
            case 'min':
                if ($numeric) {
                    return (float) $str >= (float) $param ? null : ":Campo deve ser no mínimo {$param}.";
                }
                return mb_strlen($str) >= (int) $param ? null : ":Campo precisa ter pelo menos {$param} caracteres.";
            case 'max':
                if ($numeric) {
                    return (float) $str <= (float) $param ? null : ":Campo deve ser no máximo {$param}.";
                }
                return mb_strlen($str) <= (int) $param ? null : ":Campo pode ter no máximo {$param} caracteres.";
            case 'in':
                return in_array($str, explode(',', (string) $param), true) ? null : 'Escolha uma opção válida para :campo.';
            case 'confirmed':
                return $str === (string) ($this->data[$field . '_confirmation'] ?? '') ? null : 'A confirmação não confere com :campo.';
            case 'url':
                return filter_var($str, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $str) ? null : 'Informe um endereço começando com http:// ou https://.';
            case 'color':
                return preg_match('/^#[0-9a-fA-F]{6}$/', $str) ? null : 'Use uma cor no formato #RRGGBB.';
            case 'slug':
                return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $str) ? null : 'Use só letras minúsculas, números e hífens.';
            case 'date':
                return strtotime($str) !== false ? null : 'Informe uma data válida.';
            case 'cpf':
                return self::validCpf($str) ? null : 'CPF inválido. Confira os números.';
            case 'password':
                return preg_match('/[A-Za-z]/', $str) && preg_match('/\d/', $str) ? null : 'A senha precisa ter letras e números.';
            case 'unique':
                [$table, $column, $except] = array_pad(explode(',', (string) $param), 3, null);
                $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :v" . ($except ? ' AND id <> :x' : '');
                $params = ['v' => $str] + ($except ? ['x' => (int) $except] : []);
                return (int) Database::value($sql, $params) === 0 ? null : 'Este valor já está em uso.';
            case 'exists':
                [$table, $column] = array_pad(explode(',', (string) $param), 2, 'id');
                return (int) Database::value("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :v", ['v' => $str]) > 0 ? null : 'Escolha uma opção válida para :campo.';
        }
        throw new \InvalidArgumentException("Regra de validação desconhecida: {$rule}");
    }

    private function add(string $field, string $message): void
    {
        $label = $this->labels[$field] ?? str_replace('_', ' ', $field);
        $message = str_replace([':Campo', ':campo'], [mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1), $label], $message);
        $this->errors[$field][] = $message;
    }

    public static function validCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen((string) $cpf) !== 11 || preg_match('/^(\d)\1{10}$/', (string) $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }
        return true;
    }
}
