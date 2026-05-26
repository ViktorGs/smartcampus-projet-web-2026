<?php
/**
 * Validation des entrées côté serveur.
 *
 * Règle d'or : ne JAMAIS faire confiance aux données du client. Même si le
 * frontend valide déjà les formulaires, on revalide tout ici (le client peut
 * être contourné). On accumule les erreurs par champ pour un retour clair.
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, string $label = null): self
    {
        $label = $label ?? $field;
        $v = $this->data[$field] ?? null;
        if ($v === null || (is_string($v) && trim($v) === '')) {
            $this->errors[$field] = "Le champ « $label » est obligatoire.";
        }
        return $this;
    }

    public function email(string $field): self
    {
        $v = $this->data[$field] ?? '';
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "Adresse e-mail invalide.";
        }
        return $this;
    }

    public function minLength(string $field, int $min): self
    {
        $v = (string)($this->data[$field] ?? '');
        if ($v !== '' && mb_strlen($v) < $min) {
            $this->errors[$field] = "Doit contenir au moins $min caractères.";
        }
        return $this;
    }

    public function in(string $field, array $allowed): self
    {
        $v = $this->data[$field] ?? null;
        if ($v !== null && $v !== '' && !in_array($v, $allowed, true)) {
            $this->errors[$field] = "Valeur non autorisée.";
        }
        return $this;
    }

    public function numericRange(string $field, float $min, float $max): self
    {
        $v = $this->data[$field] ?? null;
        if ($v !== null && $v !== '') {
            if (!is_numeric($v) || $v < $min || $v > $max) {
                $this->errors[$field] = "Doit être un nombre entre $min et $max.";
            }
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** Si la validation échoue, renvoie immédiatement une réponse 422. */
    public function validateOrFail(): void
    {
        if ($this->fails()) {
            Response::error('Données invalides.', 422, $this->errors);
        }
    }
}
