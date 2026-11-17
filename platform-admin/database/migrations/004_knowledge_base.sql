-- Run once after schema.sql and migrations 002 and 003.
CREATE TABLE platform_kb_articles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(180) NOT NULL,
  title VARCHAR(220) NOT NULL,
  summary VARCHAR(500) NOT NULL,
  category VARCHAR(100) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  created_by_admin_id BIGINT UNSIGNED NULL,
  updated_by_admin_id BIGINT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kb_slug (slug),
  KEY idx_kb_status_category (status,category),
  FULLTEXT KEY ft_kb_content (title,summary,body),
  CONSTRAINT fk_kb_created_admin FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_kb_updated_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial customer guidance for workflows that are implemented in this release.
INSERT INTO platform_kb_articles
  (slug,title,summary,category,body,status,published_at)
VALUES
  (
    'create-your-smartnow-account',
    'Create your SmartNow customer account',
    'Register with your email address, verify it and sign in to the customer portal.',
    'Account & Support',
    '1. Open the customer portal and choose Create account.\n2. Enter your name and email address. Organisation, phone number and country code are optional.\n3. Choose a password with at least 14 characters and confirm it.\n4. Submit the form. SmartNow sends a verification link to the email address you supplied.\n5. Open the verification link within 24 hours, then return to the portal and sign in.\n\nIf the email does not arrive, check your spam folder and use Resend verification.\n\nCreating an account does not currently activate a product licence or subscription. Product subscriptions and payments are not yet enabled.',
    'published',
    NOW()
  ),
  (
    'reset-your-customer-account-password',
    'Reset your customer account password',
    'Use the password reset page to request a secure, time-limited reset link.',
    'Account & Support',
    '1. Open Customer sign in and choose Forgot password.\n2. Enter the email address associated with your verified customer account.\n3. If the account is eligible, a password reset link is sent to that email address.\n4. Open the link within one hour, enter a new password of at least 14 characters and confirm it.\n5. Return to the sign-in page and use the new password.\n\nFor security, the reset page gives the same confirmation whether or not an account exists for the supplied email. Other active customer sessions are invalidated when the password changes.',
    'published',
    NOW()
  ),
  (
    'submit-a-support-request',
    'Submit a support request, bug report or improvement idea',
    'Use the support centre to open a request and follow replies from the SmartNow team.',
    'Account & Support',
    '1. Sign in to your verified customer account.\n2. Open Support centre from your customer dashboard.\n3. Choose Support question, Report a bug or Suggest an improvement.\n4. Enter a clear subject and describe what happened or what you would like improved. Do not include passwords or payment-card details.\n5. Submit the request. It will appear in Your support requests.\n6. Open the request to read public replies and send further information.\n\nIf you reply to a resolved or closed request, it is reopened. Internal administrator notes are not shown in your customer conversation.',
    'published',
    NOW()
  ),
  (
    'manage-your-customer-profile',
    'Manage your customer profile and password',
    'Update your contact information or change your password from the customer portal.',
    'Account & Support',
    '1. Sign in to your SmartNow customer account.\n2. Open Edit profile from the customer dashboard.\n3. Update your contact name, organisation, phone number or two-letter country code and save.\n4. To change your password, enter your current password and a new password of at least 14 characters, then confirm it.\n\nYour verified email address cannot currently be changed from the profile page. Contact SmartNow support if your account email needs correction.',
    'published',
    NOW()
  );
