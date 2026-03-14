<?php

namespace Database\Seeders;

use App\Models\Email;
use App\Models\Item;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmailSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if already seeded
        if (Email::count() > 0) {
            return;
        }

        $emails = [
            // ~5 inbox
            ['from_address' => 'mike@acmecorp.com', 'from_name' => 'Mike Chen', 'subject' => 'Q2 budget proposal needs your sign-off', 'body_text' => "Hi Sal,\n\nI've attached the Q2 budget proposal for the marketing department. We need your approval before end of week so we can submit it to finance.\n\nThe main changes from Q1 are a 15% increase in digital ad spend and a new line item for the conference sponsorship we discussed.\n\nLet me know if you have any questions.\n\nBest,\nMike", 'status' => 'inbox', 'received_at' => now()->subHours(2)],
            ['from_address' => 'sarah@designstudio.io', 'from_name' => 'Sarah Palmer', 'subject' => 'Website redesign mockups ready for review', 'body_text' => "Hey Sal,\n\nThe mockups for the homepage and about page redesign are ready. I've uploaded them to the shared Figma project.\n\nKey changes:\n- Simplified navigation with mega menu\n- New hero section with video background\n- Testimonials carousel on the homepage\n\nWould love your feedback by Thursday if possible. We're aiming to start development next week.\n\nThanks!\nSarah", 'status' => 'inbox', 'received_at' => now()->subHours(5)],
            ['from_address' => 'notifications@github.com', 'from_name' => 'GitHub', 'subject' => '[salmaster/api] Pull request #47: Fix rate limiting middleware', 'body_text' => "dependabot opened a pull request in salmaster/api:\n\nFix rate limiting middleware\n\nThis PR addresses the issue where rate limiting headers were not being set correctly for authenticated API users. The X-RateLimit-Remaining header was always showing 0 after the first request.\n\nChanges:\n- Fixed header calculation in RateLimitMiddleware\n- Added unit tests for rate limit header values\n- Updated documentation\n\nView it on GitHub: https://github.com/salmaster/api/pull/47", 'status' => 'inbox', 'received_at' => now()->subHours(8)],
            ['from_address' => 'jenny@recruitflow.com', 'from_name' => 'Jenny Tran', 'subject' => 'Interview candidate feedback needed - Senior Dev role', 'body_text' => "Hi Sal,\n\nHope your week is going well! We need your interview feedback for David Kim who interviewed for the Senior Developer position last Tuesday.\n\nCould you fill out the scorecard in RecruitFlow by end of day tomorrow? The hiring committee meets Friday morning.\n\nHere's the direct link to his profile: https://recruitflow.com/candidates/dk-4829\n\nThanks for your time!\nJenny", 'status' => 'inbox', 'received_at' => now()->subDay()],
            ['from_address' => 'alex@vendorlogistics.com', 'from_name' => 'Alex Rivera', 'subject' => 'Invoice #8842 - outstanding balance reminder', 'body_text' => "Dear Sal,\n\nThis is a friendly reminder that invoice #8842 dated February 15th for \$3,450.00 is still outstanding. The payment was due on March 1st.\n\nCould you please arrange payment at your earliest convenience? If you've already sent payment, please disregard this message.\n\nPayment details are on the original invoice. Let me know if you need a copy resent.\n\nRegards,\nAlex Rivera\nAccounts Receivable", 'status' => 'inbox', 'received_at' => now()->subDays(2)],

            // ~3 next-action
            ['from_address' => 'tom@infrateam.dev', 'from_name' => 'Tom Bradley', 'subject' => 'Server migration scheduled for Saturday night', 'body_text' => "Team,\n\nJust confirming the production server migration is scheduled for this Saturday at 11 PM EST. Expected downtime is 2-3 hours.\n\nAction items before migration:\n1. Verify all database backups completed\n2. Update DNS TTL to 300 seconds by Friday\n3. Notify customers via status page\n\nI'll send another reminder Friday afternoon.\n\nTom", 'status' => 'next-action', 'context' => '@computer', 'received_at' => now()->subDays(3)],
            ['from_address' => 'lisa@boardroom.co', 'from_name' => 'Lisa Park', 'subject' => 'Board deck due next Tuesday - your slides needed', 'body_text' => "Hi Sal,\n\nReminder that the Q1 board deck is due next Tuesday. I need your product update slides (3-4 slides max) by Monday EOD so I can compile everything.\n\nPlease cover:\n- Key metrics vs targets\n- Product roadmap highlights\n- Any risks or blockers\n\nUse the template I shared last quarter. Let me know if you need the link again.\n\nThanks,\nLisa", 'status' => 'next-action', 'context' => '@computer', 'received_at' => now()->subDays(4)],
            ['from_address' => 'support@cloudhost.io', 'from_name' => 'CloudHost Support', 'subject' => 'Your SSL certificate expires in 14 days', 'body_text' => "Hello,\n\nThis is an automated reminder that the SSL certificate for tasks.salmaster.dev expires on March 27, 2026.\n\nTo avoid any service disruption, please renew your certificate before the expiration date. You can renew it from your CloudHost dashboard under Security > SSL Certificates.\n\nIf you have auto-renewal enabled, no action is needed.\n\nCloudHost Support Team", 'status' => 'next-action', 'received_at' => now()->subDays(1)],

            // ~2 waiting
            ['from_address' => 'dana@legalteam.com', 'from_name' => 'Dana Wells', 'subject' => 'NDA with TechPartner Inc - awaiting their countersignature', 'body_text' => "Hi Sal,\n\nWe've sent the NDA to TechPartner Inc for countersignature via DocuSign. Their legal team said they'd review it within 5 business days.\n\nI'll follow up with them if we don't hear back by next Wednesday. No action needed on your end for now.\n\nBest,\nDana", 'status' => 'waiting', 'waiting_for' => 'Dana Wells', 'received_at' => now()->subDays(5)],
            ['from_address' => 'marcus@designops.co', 'from_name' => 'Marcus Johnson', 'subject' => 'Brand guidelines v2 - final review in progress', 'body_text' => "Hey Sal,\n\nJust a heads up that the updated brand guidelines are in final review with the creative director. She's out this week but promised to get feedback by next Monday.\n\nOnce approved I'll send you the final PDF and the updated Figma component library.\n\nCheers,\nMarcus", 'status' => 'waiting', 'waiting_for' => 'Marcus Johnson', 'received_at' => now()->subDays(6)],

            // ~1 done
            ['from_address' => 'noreply@calendar.google.com', 'from_name' => 'Google Calendar', 'subject' => 'Reminder: Team standup moved to 10 AM starting Monday', 'body_text' => "This is a reminder that the recurring Team Standup event has been updated.\n\nNew time: Monday-Friday, 10:00 AM - 10:15 AM EST\nPrevious time: 9:30 AM - 9:45 AM EST\n\nOrganizer: Sal\nLocation: Google Meet (link in calendar event)\n\nThis change takes effect starting next Monday.", 'status' => 'done', 'received_at' => now()->subWeek()],

            // ~1 someday
            ['from_address' => 'events@techconf.org', 'from_name' => 'TechConf 2026', 'subject' => 'Speaker applications now open for TechConf 2026', 'body_text' => "Hi Sal,\n\nWe're excited to announce that speaker applications for TechConf 2026 are now open! The conference takes place September 15-17 in Austin, TX.\n\nWe're looking for talks on:\n- Developer tooling and productivity\n- Modern web architecture\n- AI/ML in production\n\nApplication deadline: June 1, 2026\n\nSubmit your proposal at https://techconf.org/speak\n\nWe'd love to have you on stage this year!\n\nThe TechConf Team", 'status' => 'someday', 'received_at' => now()->subDays(3)],
        ];

        foreach ($emails as $emailData) {
            $status = $emailData['status'];
            $context = $emailData['context'] ?? null;
            $waitingFor = $emailData['waiting_for'] ?? null;
            $receivedAt = $emailData['received_at'];
            unset($emailData['status'], $emailData['context'], $emailData['waiting_for'], $emailData['received_at']);

            $item = Item::create([
                'id' => Str::ulid(),
                'title' => $emailData['subject'],
                'status' => $status,
                'context' => $context,
                'waiting_for' => $waitingFor,
            ]);

            Email::create([
                'id' => Str::ulid(),
                'item_id' => $item->id,
                'from_address' => $emailData['from_address'],
                'from_name' => $emailData['from_name'],
                'to_address' => 'sal@salmaster.dev',
                'subject' => $emailData['subject'],
                'body_text' => $emailData['body_text'],
                'received_at' => $receivedAt,
            ]);
        }
    }
}
