<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'subject',
        'email_body',
        'sms_body',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Render template with provided data.
     */
    public function render(array $data): array
    {
        $subject = $this->replaceVariables($this->subject, $data);
        $emailBody = $this->replaceVariables($this->email_body, $data);
        $smsBody = $this->replaceVariables($this->sms_body, $data);

        return [
            'subject' => $subject,
            'email_body' => $emailBody,
            'sms_body' => $smsBody,
        ];
    }

    /**
     * Replace template variables with actual data.
     */
    private function replaceVariables(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, $value, $template);
        }

        return $template;
    }

    /**
     * Validate that all required variables are present in data.
     */
    public function validateData(array $data): bool
    {
        $requiredVars = $this->variables ?? [];
        
        foreach ($requiredVars as $var) {
            if (!isset($data[$var])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get active templates by type.
     */
    public static function getByType(string $type): ?self
    {
        return self::where('type', $type)
            ->where('is_active', true)
            ->first();
    }
}
