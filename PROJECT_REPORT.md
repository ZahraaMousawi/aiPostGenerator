# تقرير مشروع وكيل منشورات المركز الوطني للذكاء الاصطناعي

## فكرة المشروع

يوفر المشروع Web Application مبنياً بـ Laravel لإنشاء منشورات احترافية للمركز الوطني للذكاء الاصطناعي لمنصات Facebook وInstagram. يدخل المستخدم موضوعاً أو خبراً أو فعالية، ثم يقوم الوكيل بإرجاع نص مدقق، عنوان مقترح، نسختين من المنشور، ثلاثة Hashtags، واقتراحاً بصرياً للصورة أو التصميم.

## آلية العمل

1. يستقبل المسار `/` واجهة المستخدم العربية RTL.
2. يرسل المستخدم النص إلى المسار `POST /generate`.
3. يتحقق `PostAgentController` من أن النص موجود ومناسب الطول.
4. تستدعي طبقة التحكم خدمة `SocialPostAgent`.
5. إذا كان `GEMINI_API_KEY` متوفراً، يستخدم الوكيل Gemini API لتوليد JSON منظّم للنص والهاشتاكات ووصف الصورة.
6. إذا لم يتوفر Gemini وكان `OPENAI_API_KEY` متوفراً، يستخدم الوكيل OpenAI Responses API كمسار بديل.
7. يولد النموذج العنوان، التصحيح، نسختي المنشور، والـ Hashtags حسب معنى الخبر المدخل.
8. يستخدم التطبيق وصف الصورة الناتج من الوكيل لتوليد صورة فعلية عبر Gemini image model أو OpenAI Images API.
9. إذا لم يكن أي مفتاح متوفراً أو تعذر الاتصال، تعرض الواجهة رسالة إعداد واضحة بدلاً من إظهار منشورات أو Hashtags ثابتة.

## التقنيات المستخدمة

- Laravel 12 لإدارة المسارات والتحقق والخدمات وBlade.
- Blade لإنشاء واجهة عربية باتجاه RTL.
- Vite وTailwind entry لبناء ملفات الواجهة.
- Laravel HTTP Client للتكامل مع OpenAI.
- Gemini API لتوليد النص المنظم والصورة عند توفر `GEMINI_API_KEY`.
- OpenAI Responses API كنقطة اتصال للنموذج اللغوي.
- OpenAI Images API لتوليد صورة مربعة للمنشور.
- CSS مخصص لتصميم واجهة تشغيل عملية بدون اعتماد على قوالب خارجية.

## إعداد النموذج اللغوي

أضف القيم التالية إلى ملف `.env` عند توفر مفتاح API:

```env
OPENAI_API_KEY=ضع_المفتاح_هنا
OPENAI_MODEL=gpt-5-mini
OPENAI_IMAGE_MODEL=gpt-image-1.5

GEMINI_API_KEY=ضع_مفتاح_Gemini_هنا
GEMINI_MODEL=gemini-3.5-flash
GEMINI_IMAGE_MODEL=gemini-3.1-flash-image
```

يعتمد Gemini على:

```text
https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
```

ويعتمد توليد النص عبر OpenAI على إرسال `model` و`input` إلى endpoint:

```text
https://api.openai.com/v1/responses
```

ويعتمد توليد الصورة على:

```text
https://api.openai.com/v1/images/generations
```

## الملفات الرئيسية

- `app/Http/Controllers/PostAgentController.php`: استقبال الطلبات والتحقق منها.
- `app/Services/SocialPostAgent.php`: منطق الوكيل والتكامل مع النموذج اللغوي والمولد الاحتياطي.
- `resources/views/welcome.blade.php`: واجهة المستخدم وعرض النتائج.
- `resources/css/app.css`: تنسيق الواجهة.
- `routes/web.php`: تعريف مسارات التطبيق.
- `config/services.php`: إعداد مفتاح OpenAI واسم النموذج.

## ملاحظات

- عند عدم إضافة مفتاح OpenAI يعرض المشروع رسالة إعداد واضحة، لأن المحتوى والوسوم والصورة يجب أن تأتي من AI.
- الهاشتاكات ليست ثابتة؛ يتم توليدها من النموذج حسب الخبر المدخل.
- يمكن لاحقاً إضافة حفظ للمنشورات في قاعدة البيانات، وتصدير PDF، وزر نسخ مباشر لكل منشور.
