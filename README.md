# حزمة دمج بوابة e-SADAD للمدفوعات مع Laravel

هذه الحزمة توفر واجهة برمجية سهلة الاستخدام للتعامل مع بوابة e-SADAD للمدفوعات من خلال بروتوكول SOAP. تتضمن الحزمة:

- خدمة SOAP متكاملة للتواصل مع واجهات e-SADAD
- تسجيل جميع العمليات في قاعدة البيانات
- واجهة برمجية بسيطة للتعامل مع عمليات الدفع
- تشفير البيانات الحساسة باستخدام المفتاح العام لـ e-SADAD

## التثبيت

```bash
composer require esadad/payment-gateway
```

## نشر ملفات الإعداد والترحيل

```bash
php artisan vendor:publish --provider="ESadad\PaymentGateway\Providers\ESadadServiceProvider"
```

## تنفيذ الترحيلات (Migrations)

بعد نشر ملفات الترحيل، يمكنك تنفيذها لإنشاء الجداول اللازمة في قاعدة البيانات:

```bash
php artisan migrate
```

هذا سيقوم بإنشاء الجداول التالية:
- `esadad_logs`: لتسجيل جميع عمليات الاتصال مع بوابة e-SADAD
- `esadad_transactions`: لتخزين تفاصيل عمليات الدفع

## الإعداد

قم بتعديل ملف `.env` وإضافة المعلومات التالية:

```
ESADAD_MERCHANT_CODE=your_merchant_code
ESADAD_MERCHANT_PASSWORD=your_merchant_password
ESADAD_SERVER_URL=Provider url
ESADAD_PUBLIC_KEY_PATH=path/to/esadad_public_key.pem
```

## الاستخدام الأساسي

```php
use ESadad\PaymentGateway\Facades\ESadad;

// المصادقة
$tokenKey = ESadad::authenticate();

// بدء عملية الدفع (إرسال OTP)
$initiationResult = ESadad::initiatePayment($tokenKey, $customerId, $customerPassword);

// طلب الدفع باستخدام OTP
$paymentResult = ESadad::requestPayment($tokenKey, $customerId, $otp, $invoiceId, $amount);

// تأكيد عملية الدفع
$confirmationResult = ESadad::confirmPayment($tokenKey, $customerId, $transactionDetails, $amount);
```

## التوثيق الكامل

للمزيد من المعلومات حول استخدام الحزمة، يرجى الاطلاع على [التوثيق الكامل](docs/README.md).
