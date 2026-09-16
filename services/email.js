// Email service
const nodemailer = require('nodemailer');

let transporter = null;

function getTransporter() {
    if (!transporter) {
        if (process.env.NODE_ENV === 'production' && process.env.SMTP_HOST) {
            transporter = nodemailer.createTransport({
                host: process.env.SMTP_HOST,
                port: parseInt(process.env.SMTP_PORT) || 465,
                secure: process.env.SMTP_SECURE === 'true',
                auth: {
                    user: process.env.SMTP_USER,
                    pass: process.env.SMTP_PASS
                }
            });
        } else {
            // Development: log emails to console
            transporter = {
                sendMail: async (options) => {
                    console.log('\n========== EMAIL (DEV MODE) ==========');
                    console.log(`To: ${options.to}`);
                    console.log(`Subject: ${options.subject}`);
                    console.log(`Body: ${options.text || options.html}`);
                    console.log('=======================================\n');
                    return { messageId: 'dev-' + Date.now() };
                }
            };
        }
    }
    return transporter;
}

async function sendVerificationEmail(email, token) {
    const baseUrl = process.env.APP_URL || 'http://localhost:3000';
    const verifyUrl = `${baseUrl}/verify-email?token=${token}`;
    
    await getTransporter().sendMail({
        from: process.env.SMTP_FROM || '"TAASCOR System" <noreply@taascor.com>',
        to: email,
        subject: 'TAASCOR - Verify Your Email Address',
        html: `
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background-color: #1B2A4A; padding: 20px; text-align: center;">
                    <h1 style="color: white; margin: 0;">TAASCOR</h1>
                    <p style="color: #D4A017; margin: 5px 0 0;">Attendance Monitoring System</p>
                </div>
                <div style="padding: 30px; background: #f9f9f9;">
                    <h2 style="color: #1B2A4A;">Email Verification</h2>
                    <p>Please click the button below to verify your email address and complete your registration.</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="${verifyUrl}" style="background-color: #1B2A4A; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Verify Email</a>
                    </div>
                    <p style="color: #666; font-size: 14px;">If the button doesn't work, copy and paste this link: <br>${verifyUrl}</p>
                    <p style="color: #666; font-size: 14px;">This link expires in 24 hours.</p>
                </div>
            </div>
        `,
        text: `Verify your TAASCOR email: ${verifyUrl}\nThis link expires in 24 hours.`
    });
}

async function sendPasswordResetEmail(email, token) {
    const baseUrl = process.env.APP_URL || 'http://localhost:3000';
    const resetUrl = `${baseUrl}/reset-password?token=${token}`;
    
    await getTransporter().sendMail({
        from: process.env.SMTP_FROM || '"TAASCOR System" <noreply@taascor.com>',
        to: email,
        subject: 'TAASCOR - Password Reset',
        html: `
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background-color: #1B2A4A; padding: 20px; text-align: center;">
                    <h1 style="color: white; margin: 0;">TAASCOR</h1>
                    <p style="color: #D4A017; margin: 5px 0 0;">Attendance Monitoring System</p>
                </div>
                <div style="padding: 30px; background: #f9f9f9;">
                    <h2 style="color: #1B2A4A;">Password Reset</h2>
                    <p>You requested a password reset. Click the button below to set a new password.</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="${resetUrl}" style="background-color: #1B2A4A; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Reset Password</a>
                    </div>
                    <p style="color: #666; font-size: 14px;">If you did not request this, please ignore this email.</p>
                    <p style="color: #666; font-size: 14px;">This link expires in 1 hour.</p>
                </div>
            </div>
        `,
        text: `Reset your TAASCOR password: ${resetUrl}\nThis link expires in 1 hour.`
    });
}

async function sendAccountApprovedEmail(email, fullName) {
    await getTransporter().sendMail({
        from: process.env.SMTP_FROM || '"TAASCOR System" <noreply@taascor.com>',
        to: email,
        subject: 'TAASCOR - Account Approved',
        html: `
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background-color: #1B2A4A; padding: 20px; text-align: center;">
                    <h1 style="color: white; margin: 0;">TAASCOR</h1>
                </div>
                <div style="padding: 30px; background: #f9f9f9;">
                    <h2 style="color: #1B2A4A;">Account Approved</h2>
                    <p>Hello ${fullName},</p>
                    <p>Your TAASCOR coordinator account has been approved. You can now log in to the system.</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="${process.env.APP_URL || 'http://localhost:3000'}/login" style="background-color: #1B2A4A; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Log In</a>
                    </div>
                </div>
            </div>
        `,
        text: `Hello ${fullName}, your TAASCOR coordinator account has been approved.`
    });
}

module.exports = { sendVerificationEmail, sendPasswordResetEmail, sendAccountApprovedEmail };
