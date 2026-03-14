<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemTag;
use App\Models\Note;
use App\Models\NoteTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StressTestSeeder extends Seeder
{
    public function run(): void
    {
        // === Projects ===
        $projects = [
            ['title' => 'Kitchen renovation', 'goal' => 'New countertops and backsplash installed by April'],
            ['title' => 'Q2 product launch', 'goal' => 'Feature shipped and announced to customers'],
            ['title' => 'Hire backend engineer', 'goal' => 'Offer accepted and start date confirmed'],
        ];

        $projectIds = [];
        foreach ($projects as $p) {
            $item = Item::create([
                'id' => Str::ulid(),
                'title' => $p['title'],
                'status' => 'project',
                'goal' => $p['goal'],
            ]);
            $projectIds[] = $item->id;
        }

        // === Next Actions ===
        $nextActions = [
            ['title' => 'Call plumber to schedule estimate', 'context' => '@phone', 'project_id' => $projectIds[0]],
            ['title' => 'Pick tile samples from Home Depot', 'context' => '@errands', 'project_id' => $projectIds[0]],
            ['title' => 'Draft launch blog post', 'context' => '@computer', 'project_id' => $projectIds[1]],
            ['title' => 'Review candidate resumes from recruiter', 'context' => '@computer', 'project_id' => $projectIds[2]],
            ['title' => 'Schedule phone screen with top 3 candidates', 'context' => '@phone', 'project_id' => $projectIds[2]],
            ['title' => 'Buy birthday gift for Mom', 'context' => '@errands', 'project_id' => null],
            ['title' => 'Review and sign lease renewal', 'context' => '@home', 'project_id' => null],
            ['title' => 'Update LinkedIn profile', 'context' => '@computer', 'project_id' => null],
            ['title' => 'Book dentist appointment', 'context' => '@phone', 'project_id' => null],
            ['title' => 'Fix broken link on company website', 'context' => '@computer', 'project_id' => null],
            ['title' => 'Prep slides for Thursday standup', 'context' => '@computer', 'project_id' => null],
            ['title' => 'Drop off dry cleaning', 'context' => '@errands', 'project_id' => null],
        ];

        foreach ($nextActions as $na) {
            Item::create([
                'id' => Str::ulid(),
                'title' => $na['title'],
                'status' => 'next-action',
                'context' => $na['context'],
                'project_id' => $na['project_id'],
            ]);
        }

        // === Waiting For ===
        $waiting = [
            ['title' => 'Plumber to confirm availability', 'waiting_for' => 'Mike the plumber', 'project_id' => $projectIds[0]],
            ['title' => 'QA sign-off on release candidate', 'waiting_for' => 'QA team', 'project_id' => $projectIds[1]],
            ['title' => 'Insurance claim reimbursement', 'waiting_for' => 'Aetna', 'project_id' => null],
            ['title' => 'Response from landlord about parking', 'waiting_for' => 'Property manager', 'project_id' => null],
        ];

        foreach ($waiting as $w) {
            Item::create([
                'id' => Str::ulid(),
                'title' => $w['title'],
                'status' => 'waiting',
                'waiting_for' => $w['waiting_for'],
                'project_id' => $w['project_id'],
            ]);
        }

        // === Inbox ===
        $inbox = [
            'Look into meal prep services',
            'Idea: automate weekly report email',
            'Check if passport needs renewal',
            'Research best standing desk under $500',
            'Follow up with Sarah about book club',
            'Cancel unused Hulu subscription',
            'Review bank statement for weird charge',
        ];

        foreach ($inbox as $title) {
            Item::create([
                'id' => Str::ulid(),
                'title' => $title,
                'status' => 'inbox',
            ]);
        }

        // === Tickler ===
        $tickler = [
            ['title' => 'Renew car registration', 'tickler_date' => now()->addDays(14)->toDateString()],
            ['title' => 'Follow up on job application', 'tickler_date' => now()->addDays(7)->toDateString()],
        ];

        foreach ($tickler as $t) {
            Item::create([
                'id' => Str::ulid(),
                'title' => $t['title'],
                'status' => 'tickler',
                'tickler_date' => $t['tickler_date'],
            ]);
        }

        // === Done ===
        $done = [
            'File quarterly taxes',
            'Set up new monitor',
            'Send thank you note to interviewer',
        ];

        foreach ($done as $title) {
            Item::create([
                'id' => Str::ulid(),
                'title' => $title,
                'status' => 'done',
                'completed_at' => now()->subDays(rand(1, 10)),
                'original_status' => 'next-action',
            ]);
        }

        // === Flagged items (flag some existing) ===
        Item::where('title', 'Review and sign lease renewal')->update(['flagged' => true]);
        Item::where('title', 'Draft launch blog post')->update(['flagged' => true]);

        // === Tags ===
        $tagRecords = [];
        $items = Item::all();
        $tagMap = [
            'Call plumber' => ['urgent'],
            'Draft launch blog post' => ['marketing'],
            'Review candidate resumes' => ['hiring'],
            'Buy birthday gift' => ['personal'],
            'Fix broken link' => ['bug'],
            'Prep slides' => ['team'],
            'Insurance claim' => ['personal', 'followup'],
        ];

        foreach ($items as $item) {
            foreach ($tagMap as $prefix => $tags) {
                if (str_starts_with($item->title, $prefix)) {
                    foreach ($tags as $tag) {
                        $tagRecords[] = ['item_id' => $item->id, 'tag' => $tag];
                    }
                }
            }
        }

        if (count($tagRecords) > 0) {
            ItemTag::insert($tagRecords);
        }

        $this->command->info('Created ' . $items->count() . ' items with ' . count($tagRecords) . ' tags.');

        // === Notes ===
        $notes = [
            ['title' => 'Kitchen renovation ideas', 'content' => "## Inspiration\n\n- White subway tile backsplash\n- Quartz countertops (Calacatta style)\n- Under-cabinet LED lighting\n\n## Budget\n\nTarget: \$8,000–\$12,000\nContractor estimate pending from Mike", 'pinned' => true],
            ['title' => 'Q2 launch checklist', 'content' => "- [ ] Blog post drafted\n- [ ] Email sequence ready\n- [ ] Landing page updated\n- [ ] Social media assets\n- [ ] Customer success briefed\n- [ ] Analytics tracking in place", 'pinned' => false],
            ['title' => 'Interview questions for backend role', 'content' => "1. Walk me through a system you designed for scale\n2. How do you approach debugging a production issue?\n3. Tell me about a time you disagreed with a technical decision\n4. What's your experience with message queues?\n5. How do you think about database indexing?", 'pinned' => false],
            ['title' => 'Books to read', 'content' => "- Getting Things Done (re-read)\n- Thinking in Systems — Donella Meadows\n- Staff Engineer — Will Larson\n- The Mom Test — Rob Fitzpatrick", 'pinned' => false],
            ['title' => 'Weekly review template', 'content' => "## Collect\n\nGather all loose papers, notes, receipts\n\n## Process\n\nEmpty inbox to zero\n\n## Review\n\n- Calendar (past week + next 2 weeks)\n- All project lists\n- Someday/maybe list\n- Waiting-for list\n\n## Reflect\n\nWhat went well? What needs attention?", 'pinned' => true],
        ];

        $noteRecords = [];
        $noteTagRecords = [];
        $noteTagMap = [
            'Kitchen' => 'reference',
            'Q2 launch' => 'work',
            'Interview' => 'hiring',
            'Books' => 'ideas',
            'Weekly review' => 'reference',
        ];

        foreach ($notes as $i => $note) {
            $id = Str::ulid();
            $noteRecords[] = [
                'id' => $id,
                'title' => $note['title'],
                'content' => $note['content'],
                'pinned' => $note['pinned'],
                'trashed' => false,
                'locked' => false,
                'created_at' => now()->subDays(30 - $i),
                'updated_at' => now()->subDays(rand(0, 5)),
            ];

            foreach ($noteTagMap as $prefix => $tag) {
                if (str_starts_with($note['title'], $prefix)) {
                    $noteTagRecords[] = ['note_id' => $id, 'tag' => $tag];
                }
            }
        }

        Note::insert($noteRecords);
        if (count($noteTagRecords) > 0) {
            NoteTag::insert($noteTagRecords);
        }

        $this->command->info('Created ' . count($noteRecords) . ' notes with ' . count($noteTagRecords) . ' tags.');
    }
}
