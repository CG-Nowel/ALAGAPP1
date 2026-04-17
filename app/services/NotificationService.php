<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Core\Database;

class NotificationService
{
    private Notification $notificationModel;
    private Database $db;
    private array $mailConfig;

    public function __construct()
    {
        $this->notificationModel = new Notification();
        $this->db = Database::getInstance();
        $this->mailConfig = require dirname(__DIR__, 2) . '/config/mail.php';
    }

    /**
     * Send an in-app notification.
     */
    public function sendInApp(int $userId, string $title, string $message, string $type = 'SYSTEM', ?string $relatedType = null, ?int $relatedId = null): int
    {
        return $this->notificationModel->createNotification($userId, $title, $message, $type, 'IN_APP', $relatedType, $relatedId);
    }

    /**
     * Send an email notification.
     */
    public function sendEmail(string $to, string $subject, string $body): bool
    {
        if (empty($this->mailConfig['host']) || empty($this->mailConfig['username'])) {
            // SMTP not configured, log and skip
            error_log("Email sending skipped - SMTP not configured. To: $to, Subject: $subject");
            return false;
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->mailConfig['username'];
            $mail->Password = $this->mailConfig['password'];
            $mail->SMTPSecure = $this->mailConfig['encryption'];
            $mail->Port = $this->mailConfig['port'];

            $mail->setFrom($this->mailConfig['from']['address'], $this->mailConfig['from']['name']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $this->wrapInTemplate($subject, $body);
            $mail->AltBody = strip_tags($body);

            return $mail->send();
        } catch (\Throwable $e) {
            error_log("Email send failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send SMS notification (placeholder).
     */
    public function sendSMS(string $phoneNumber, string $message): bool
    {
        // Placeholder for SMS integration
        // Integration with Twilio, Nexmo, Semaphore, etc. can be added here
        error_log("SMS placeholder - To: $phoneNumber, Message: $message");
        return true;
    }

    /**
     * Send email verification.
     */
    public function sendEmailVerification(int $userId, string $email, string $token): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $url = $config['url'] . '/verify-email?token=' . $token;

        $body = "
            <h2>Verify Your Email Address</h2>
            <p>Thank you for registering with PediCare Clinic. Please click the button below to verify your email address.</p>
            <p style='text-align:center;margin:30px 0;'>
                <a href='{$url}' style='background-color:#FF6B9A;color:white;padding:12px 30px;text-decoration:none;border-radius:5px;font-weight:bold;'>
                    Verify Email
                </a>
            </p>
            <p>Or copy and paste this link: <br><a href='{$url}'>{$url}</a></p>
            <p>This link will expire in 24 hours.</p>
        ";

        $this->sendEmail($email, 'Verify Your Email - PediCare Clinic', $body);

        $this->sendInApp($userId, 'Email Verification Required', 'Please check your email to verify your account.', 'SYSTEM');
    }

    /**
     * Send password reset email.
     */
    public function sendPasswordReset(int $userId, string $email, string $token): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $url = $config['url'] . '/reset-password?token=' . $token;

        $body = "
            <h2>Reset Your Password</h2>
            <p>We received a request to reset your password. Click the button below to set a new password.</p>
            <p style='text-align:center;margin:30px 0;'>
                <a href='{$url}' style='background-color:#FF6B9A;color:white;padding:12px 30px;text-decoration:none;border-radius:5px;font-weight:bold;'>
                    Reset Password
                </a>
            </p>
            <p>Or copy and paste this link: <br><a href='{$url}'>{$url}</a></p>
            <p>This link will expire in 1 hour. If you did not request this, please ignore this email.</p>
        ";

        $this->sendEmail($email, 'Password Reset - PediCare Clinic', $body);
    }

    /**
     * Send doctor credentials.
     */
    public function sendDoctorCredentials(int $userId, string $email, string $tempPassword): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $loginUrl = $config['url'] . '/login';

        $body = "
            <h2>Your Doctor Account Has Been Created</h2>
            <p>Welcome to PediCare Clinic! An account has been created for you. Here are your login credentials:</p>
            <div style='background:#f5f5f5;padding:15px;border-radius:5px;margin:20px 0;'>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Temporary Password:</strong> {$tempPassword}</p>
            </div>
            <p>You will be required to change your password upon first login.</p>
            <p style='text-align:center;margin:30px 0;'>
                <a href='{$loginUrl}' style='background-color:#FF6B9A;color:white;padding:12px 30px;text-decoration:none;border-radius:5px;font-weight:bold;'>
                    Login Now
                </a>
            </p>
        ";

        $this->sendEmail($email, 'Your PediCare Clinic Account', $body);
        $this->sendInApp($userId, 'Welcome to PediCare', 'Please change your temporary password after your first login.', 'SYSTEM');
    }

    /**
     * Send appointment reminder.
     */
    public function sendAppointmentReminder(int $appointmentId): void
    {
        $appointment = $this->db->fetchOne(
            "SELECT a.*, p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                    p.parent_id, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                    par.email AS parent_email, par.phone AS parent_phone
             FROM appointments a
             JOIN patients p ON a.patient_id = p.id
             JOIN users d ON a.doctor_id = d.id
             JOIN users par ON p.parent_id = par.id
             WHERE a.id = ?",
            [$appointmentId]
        );

        if (!$appointment) {
            return;
        }

        $date = date('F j, Y', strtotime($appointment['appointment_date']));
        $time = date('g:i A', strtotime($appointment['appointment_time']));
        $doctorName = "Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']}";

        $message = "Reminder: {$appointment['patient_first_name']}'s appointment with {$doctorName} is tomorrow, {$date} at {$time}.";

        $this->sendInApp((int) $appointment['parent_id'], 'Appointment Reminder', $message, 'REMINDER', 'appointment', $appointmentId);
        $this->sendEmail($appointment['parent_email'], 'Appointment Reminder - PediCare Clinic', "<p>{$message}</p>");

        if ($appointment['parent_phone']) {
            $this->sendSMS($appointment['parent_phone'], $message);
        }
    }

    /**
     * Get notifications for the bell dropdown.
     */
    public function getForBell(int $userId): array
    {
        return [
            'unread_count' => $this->notificationModel->getUnreadCount($userId),
            'notifications' => $this->notificationModel->getUnread($userId, 10),
        ];
    }

    /**
     * Wrap email body in a styled template.
     */
    private function wrapInTemplate(string $title, string $body): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='utf-8'></head>
        <body style='font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;'>
            <div style='background:linear-gradient(135deg,#FF6B9A,#FF8FB1);padding:20px;text-align:center;'>
                <h1 style='color:white;margin:0;font-size:24px;'>PediCare Clinic</h1>
            </div>
            <div style='padding:30px;background:white;'>
                {$body}
            </div>
            <div style='padding:20px;text-align:center;color:#999;font-size:12px;background:#f9f9f9;'>
                <p>PediCare Pediatric Clinic &copy; " . date('Y') . "</p>
                <p>This is an automated message. Please do not reply directly to this email.</p>
            </div>
        </body>
        </html>";
    }
}
