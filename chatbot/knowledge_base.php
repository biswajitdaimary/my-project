<?php
/**
 * PowerHouse Gym — Chatbot Knowledge Base
 * 25 intents | 200+ keyword triggers | Dynamic DB-aware responses
 */

if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

// ── Intent Definitions ───────────────────────────────────────────────────────
// Each intent has: keywords[], response (text), action (link button), quick_replies[]

function chatbot_get_intents(string $gymName = 'PowerHouse Gym'): array
{
    $base = SITE_URL;
    return [

        // ── 1. Greeting ──────────────────────────────────────────────────────
        'greeting' => [
            'keywords' => ['hello','hi','hey','good morning','good evening','good afternoon','howdy','sup','greetings','namaste','hola'],
            'response' => "👋 Hey there! Welcome to **{$gymName}**! I'm your personal fitness assistant. How can I help you today?",
            'action'   => null,
            'quick_replies' => ['View Membership Plans','Book a Trainer','Calculate My BMI','See Class Schedule'],
        ],

        // ── 2. Farewell ──────────────────────────────────────────────────────
        'farewell' => [
            'keywords' => ['bye','goodbye','see you','take care','later','cya','good night','quit','exit','done'],
            'response' => "💪 Thanks for stopping by! Keep pushing your limits. See you at **{$gymName}**! 🏋️",
            'action'   => null,
            'quick_replies' => ['Back to Home'],
        ],

        // ── 3. Membership Plans ───────────────────────────────────────────────
        'plans' => [
            'keywords' => ['plan','plans','membership','pricing','price','cost','fees','monthly','annual','package','packages','subscribe','subscription','join','rate','rates','how much','charges'],
            'response' => "🏆 We offer flexible membership plans:\n\n• **Basic** — Gym access + Locker\n• **Premium** — Basic + Trainer sessions + Diet plan\n• **Elite** — All Premium features + Priority booking\n\nClick below to see current prices and join!",
            'action'   => ['label' => '💳 View All Plans', 'url' => $base . '/plans.php'],
            'quick_replies' => ['How do I pay?','Book a Trainer','Contact Us'],
        ],

        // ── 4. Payment / Checkout ─────────────────────────────────────────────
        'payment' => [
            'keywords' => ['pay','payment','checkout','razorpay','upi','card','debit','credit','netbanking','wallet','purchase','buy','transaction','invoice','receipt','bill'],
            'response' => "💳 We use **Razorpay** for 100% secure payments. You can pay via:\n\n✅ UPI (GPay, PhonePe, Paytm)\n✅ Debit/Credit Cards\n✅ Net Banking\n✅ Digital Wallets\n\nAll transactions are 256-bit SSL encrypted.",
            'action'   => ['label' => '🔒 Go to Checkout', 'url' => $base . '/plans.php'],
            'quick_replies' => ['View Plans','Payment Failed?','Contact Support'],
        ],

        // ── 5. Trainers ──────────────────────────────────────────────────────
        'trainers' => [
            'keywords' => ['trainer','trainers','coach','coaches','instructor','personal trainer','fitness expert','pt','training','personal training','who trains','who teaches'],
            'response' => "🏋️ Our certified trainers specialize in:\n\n💪 Weight Training & Powerlifting\n🧘 Yoga & Flexibility\n🏃 Cardio & HIIT\n🥊 Boxing & MMA\n\nView all trainer profiles and check their availability!",
            'action'   => ['label' => '👥 Meet Our Trainers', 'url' => $base . '/trainers.php'],
            'quick_replies' => ['Book a Trainer','View Schedule','View Plans'],
        ],

        // ── 6. Book a Trainer ─────────────────────────────────────────────────
        'book_trainer' => [
            'keywords' => ['book','booking','book trainer','schedule trainer','appointment','slot','reserve','session','personal session','book session','trainer appointment','schedule session'],
            'response' => "📅 You can book a personal trainer session directly from your member dashboard!\n\nSteps:\n1️⃣ Log in to your account\n2️⃣ Go to **Book a Trainer**\n3️⃣ Choose your trainer & time slot\n4️⃣ Confirm your booking",
            'action'   => ['label' => '📅 Book a Session', 'url' => $base . '/user/book-trainer.php'],
            'quick_replies' => ['View Trainers','My Bookings','Login'],
        ],

        // ── 7. Class Schedule ─────────────────────────────────────────────────
        'schedule' => [
            'keywords' => ['schedule','timetable','class','classes','timing','timings','time table','yoga class','zumba','aerobics','when','what time','class time','gym hours','open','opening hours','close','closing'],
            'response' => "🕐 **Gym Hours:**\n• Mon–Fri: 5:00 AM – 10:00 PM\n• Saturday: 6:00 AM – 8:00 PM\n• Sunday: 7:00 AM – 6:00 PM\n\nView our full weekly class schedule below!",
            'action'   => ['label' => '📋 View Schedule', 'url' => $base . '/schedule.php'],
            'quick_replies' => ['Book a Trainer','Contact Us'],
        ],

        // ── 8. BMI Calculator ─────────────────────────────────────────────────
        'bmi' => [
            'keywords' => ['bmi','body mass','weight','height','calculate bmi','bmi calculator','body fat','ideal weight','healthy weight','overweight','underweight','obesity','fit','fitness level','body index'],
            'response' => "📊 Use our free **BMI Calculator** to check your Body Mass Index!\n\n🟢 BMI < 18.5 = Underweight\n🟢 18.5–24.9 = Normal\n🟡 25–29.9 = Overweight\n🔴 30+ = Obese\n\nYour BMI history is also saved in your profile!",
            'action'   => ['label' => '📊 Calculate BMI', 'url' => $base . '/bmi-calculator.php'],
            'quick_replies' => ['View My BMI History','View Plans'],
        ],

        // ── 9. Register / Sign Up ─────────────────────────────────────────────
        'register' => [
            'keywords' => ['register','sign up','signup','create account','new account','enroll','enrolment','join now','become member','membership registration','open account'],
            'response' => "🎉 Joining PowerHouse Gym is easy!\n\n1️⃣ Click **Register** below\n2️⃣ Fill your details\n3️⃣ Choose a membership plan\n4️⃣ Complete payment\n5️⃣ Start your fitness journey! 💪",
            'action'   => ['label' => '📝 Register Now', 'url' => $base . '/auth/register.php'],
            'quick_replies' => ['View Plans','Login'],
        ],

        // ── 10. Login ─────────────────────────────────────────────────────────
        'login' => [
            'keywords' => ['login','log in','sign in','signin','my account','access account','account access','member login','dashboard','portal'],
            'response' => "🔑 Already a member? Log in to access your:\n\n• Membership status\n• Trainer bookings\n• BMI history\n• Payment receipts\n• Notifications",
            'action'   => ['label' => '🔑 Login Now', 'url' => $base . '/auth/login.php'],
            'quick_replies' => ['Forgot Password?','Register'],
        ],

        // ── 11. Forgot Password ───────────────────────────────────────────────
        'forgot_password' => [
            'keywords' => ['forgot','password','reset password','forgot password','cant login','cannot login','lost password','recover','account recovery','change password'],
            'response' => "🔒 No worries! Reset your password in seconds:\n\n1️⃣ Click the link below\n2️⃣ Enter your registered email\n3️⃣ Check your inbox for a reset link\n4️⃣ Set a new password",
            'action'   => ['label' => '🔒 Reset Password', 'url' => $base . '/auth/forgot-password.php'],
            'quick_replies' => ['Login','Contact Support'],
        ],

        // ── 12. Gallery ───────────────────────────────────────────────────────
        'gallery' => [
            'keywords' => ['gallery','photos','pictures','images','gym photos','facility','facilities','equipment','machines','look','how does','tour','virtual tour','gym look'],
            'response' => "📸 Check out our **state-of-the-art facilities**!\n\nWe have:\n🏋️ Free weights & machines\n🏊 Swimming pool\n🧘 Dedicated yoga studio\n🥊 Boxing ring\n🚿 Premium locker rooms",
            'action'   => ['label' => '📸 View Gallery', 'url' => $base . '/gallery.php'],
            'quick_replies' => ['View Plans','Contact Us'],
        ],

        // ── 13. Contact Us ────────────────────────────────────────────────────
        'contact' => [
            'keywords' => ['contact','reach','call','phone','email','address','location','where','find','direction','map','help','support','customer care','helpline','whatsapp','message us'],
            'response' => "📞 **Get in Touch with Us:**\n\n📍 123 Fitness Street, Gym City\n📞 +1 234 567 890\n📧 info@powerhousegym.com\n\nOr fill out our contact form and we'll reply within 24 hours!",
            'action'   => ['label' => '📬 Contact Us', 'url' => $base . '/contact.php'],
            'quick_replies' => ['View Plans','Book a Trainer'],
        ],

        // ── 14. About Us ──────────────────────────────────────────────────────
        'about' => [
            'keywords' => ['about','about us','who are you','history','story','mission','vision','founded','established','background','team','our team','gym info','information'],
            'response' => "🏆 **{$gymName}** was founded with a single mission: *Transform lives through fitness.*\n\nWe are a premium fitness center with:\n✅ 10+ years of experience\n✅ 50+ certified trainers\n✅ 5,000+ active members\n✅ World-class equipment",
            'action'   => ['label' => 'ℹ️ About Us', 'url' => $base . '/about.php'],
            'quick_replies' => ['View Plans','Meet Trainers'],
        ],

        // ── 15. Blog ──────────────────────────────────────────────────────────
        'blog' => [
            'keywords' => ['blog','article','articles','post','posts','tips','fitness tips','nutrition','diet','workout','exercise tips','advice','read','guides','news'],
            'response' => "📰 Stay informed with our **Fitness Blog**!\n\nTopics we cover:\n💪 Workout routines for all levels\n🥗 Nutrition & diet guides\n🧠 Mental health & wellness\n🏃 Cardio & weight loss tips",
            'action'   => ['label' => '📰 Read Our Blog', 'url' => $base . '/blog.php'],
            'quick_replies' => ['Calculate BMI','View Plans'],
        ],

        // ── 16. My Dashboard ──────────────────────────────────────────────────
        'dashboard' => [
            'keywords' => ['dashboard','my dashboard','member area','my profile','profile','account','my membership','member portal','user area','my bookings','my payments'],
            'response' => "📊 Your **Member Dashboard** gives you access to:\n\n• 📋 Membership status & renewal\n• 📅 Trainer bookings\n• 📊 BMI history & progress\n• 💳 Payment history\n• 🔔 Notifications",
            'action'   => ['label' => '📊 My Dashboard', 'url' => $base . '/user/dashboard.php'],
            'quick_replies' => ['My Membership','My Bookings','BMI History'],
        ],

        // ── 17. My Membership Status ──────────────────────────────────────────
        'membership_status' => [
            'keywords' => ['my membership','membership status','active membership','membership expire','when expire','renewal','renew','validity','membership valid','expiry date','end date','days left'],
            'response' => "🎫 Check your **membership status** anytime from your profile!\n\nYou can view:\n• Start & end date\n• Plan name & features\n• Sessions remaining\n• Renewal options",
            'action'   => ['label' => '🎫 My Membership', 'url' => $base . '/user/membership.php'],
            'quick_replies' => ['Renew Membership','View Plans','My Dashboard'],
        ],

        // ── 18. My Bookings ───────────────────────────────────────────────────
        'my_bookings' => [
            'keywords' => ['my booking','my bookings','booking history','appointments','upcoming session','past booking','cancel booking','reschedule','my sessions','trainer session','session history'],
            'response' => "📅 View all your **trainer appointments** in one place!\n\n• ✅ Upcoming sessions\n• 📜 Past session history\n• ❌ Cancel or reschedule\n• ⭐ Rate your trainer",
            'action'   => ['label' => '📅 My Bookings', 'url' => $base . '/user/bookings.php'],
            'quick_replies' => ['Book a Trainer','My Dashboard'],
        ],

        // ── 19. Payment History ───────────────────────────────────────────────
        'payment_history' => [
            'keywords' => ['payment history','my payments','transaction history','receipt','invoice','payment receipt','past payment','billing history','payment record','payment details'],
            'response' => "💳 Access your complete **payment history** from your account:\n\n• Payment date & amount\n• Plan purchased\n• Payment ID & status\n• Download receipts",
            'action'   => ['label' => '💳 Payment History', 'url' => $base . '/user/payments.php'],
            'quick_replies' => ['My Dashboard','Contact Support'],
        ],

        // ── 20. Notifications ─────────────────────────────────────────────────
        'notifications' => [
            'keywords' => ['notification','notifications','alert','alerts','updates','news','reminder','message','inbox','unread'],
            'response' => "🔔 Check your **notifications** for:\n\n• Booking confirmations\n• Membership renewal reminders\n• Payment receipts\n• Special offers & announcements",
            'action'   => ['label' => '🔔 Notifications', 'url' => $base . '/user/notifications.php'],
            'quick_replies' => ['My Dashboard'],
        ],

        // ── 21. Diet / Nutrition ──────────────────────────────────────────────
        'diet' => [
            'keywords' => ['diet','nutrition','food','eat','meal plan','meal','calorie','protein','carbs','supplement','weight loss diet','muscle gain diet','what to eat','healthy food'],
            'response' => "🥗 Our **Premium & Elite** plans include a personalized **Diet Plan** crafted by certified nutritionists!\n\nFor general tips, check our fitness blog. For a personal diet plan, upgrade your membership or book a trainer session.",
            'action'   => ['label' => '📰 Fitness Blog', 'url' => $base . '/blog.php'],
            'quick_replies' => ['View Plans','Book a Trainer','Calculate BMI'],
        ],

        // ── 22. Weight Loss / Muscle Gain ─────────────────────────────────────
        'fitness_goals' => [
            'keywords' => ['weight loss','lose weight','fat loss','burn fat','muscle','muscle gain','build muscle','bulk','strength','tone','toning','transformation','body transformation','six pack','abs','slim','shred'],
            'response' => "🎯 Achieving your fitness goals starts with the right plan!\n\n**For Weight Loss:** Cardio + HIIT + Diet\n**For Muscle Gain:** Weight Training + Protein-rich diet\n**For Toning:** Circuit training + Yoga\n\nOur trainers will create a custom plan just for you!",
            'action'   => ['label' => '👥 Meet Trainers', 'url' => $base . '/trainers.php'],
            'quick_replies' => ['Book a Trainer','Calculate BMI','View Plans'],
        ],

        // ── 23. Trial / Free Pass ─────────────────────────────────────────────
        'trial' => [
            'keywords' => ['trial','free trial','free pass','day pass','visitor pass','try','demo','test','one day pass','one-day','first visit','free workout'],
            'response' => "🎟️ Interested in trying out the gym?\n\nContact us to request a **Free Day Pass** or a guided tour of our facilities. We'd love to show you around!\n\n📞 Call us or fill out the contact form.",
            'action'   => ['label' => '📬 Contact Us', 'url' => $base . '/contact.php'],
            'quick_replies' => ['View Plans','View Gallery'],
        ],

        // ── 24. Privacy & Terms ───────────────────────────────────────────────
        'legal' => [
            'keywords' => ['privacy','terms','policy','refund','cancellation','legal','terms of service','privacy policy','data','gdpr','rules','regulation','conditions','agreement'],
            'response' => "📄 You can review our full legal policies here:\n\n• 🔒 Privacy Policy\n• 📋 Terms & Conditions\n• 💰 Refund Policy\n\nFor specific concerns, feel free to contact our support team.",
            'action'   => ['label' => '🔒 Privacy Policy', 'url' => $base . '/privacy.php'],
            'quick_replies' => ['Terms of Service','Contact Support'],
        ],

        // ── 25. Fallback ─────────────────────────────────────────────────────
        'fallback' => [
            'keywords' => [],
            'response' => "🤔 I didn't quite catch that. Here are some things I can help you with:",
            'action'   => null,
            'quick_replies' => ['View Plans','Book a Trainer','Class Schedule','Contact Us','Calculate BMI','About Us'],
        ],
    ];
}


// ── Intent Matcher ────────────────────────────────────────────────────────────

function chatbot_match_intent(string $userInput, array $intents): string
{
    $input   = strtolower(trim($userInput));
    $words   = preg_split('/[^\p{L}\p{N}]+/u', $input, -1, PREG_SPLIT_NO_EMPTY);
    $wordSet = array_fill_keys($words ?: [], true);
    $best    = 'fallback';
    $topScore = 0;

    foreach ($intents as $intentKey => $intent) {
        if ($intentKey === 'fallback') continue;
        $score = 0;
        foreach ($intent['keywords'] as $kw) {
            $keyword = strtolower(trim($kw));
            $isPhrase = str_word_count($keyword) > 1;

            if (($isPhrase && str_contains($input, $keyword)) || (!$isPhrase && isset($wordSet[$keyword]))) {
                $score += $isPhrase ? 3 : 1;
            }
        }
        if ($score > $topScore) {
            $topScore = $score;
            $best     = $intentKey;
        }
    }
    return $best;
}


// ── Dynamic DB Data Injection ─────────────────────────────────────────────────

function chatbot_enrich_response(string $intentKey, array $intent, PDO $pdo): array
{
    try {
        if ($intentKey === 'plans') {
            $rows = $pdo->query("SELECT plan_name, price, duration_days FROM membership_plans WHERE is_active = 1 ORDER BY price ASC LIMIT 3")->fetchAll();
            if ($rows) {
                $lines = array_map(fn($r) => "• **{$r['plan_name']}** — ₹{$r['price']} / {$r['duration_days']} days", $rows);
                $intent['response'] = "🏆 **Current Membership Plans:**\n\n" . implode("\n", $lines) . "\n\nClick below to view full details & join!";
            }
        }

        if ($intentKey === 'trainers') {
            $count = $pdo->query("SELECT COUNT(*) FROM trainers WHERE is_active = 1")->fetchColumn();
            if ($count) {
                $intent['response'] = "🏋️ We have **{$count} certified trainers** ready to help you achieve your goals!\n\nSpecializations include Weight Training, Yoga, HIIT, Boxing & more.";
            }
        }

        if ($intentKey === 'schedule') {
            $gymName = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'gym_name'")->fetchColumn();
            if ($gymName) {
                $intent['response'] = str_replace('PowerHouse Gym', $gymName, $intent['response']);
            }
        }
    } catch (Throwable $e) {
        // Silently fall back to static response
    }
    return $intent;
}
