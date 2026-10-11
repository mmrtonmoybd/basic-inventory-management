<?php

namespace App\Console\Commands;

use App\Services\Bangla;
use App\Enums\Role;
use App\Models\User;
use App\Services\DemoDataService;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class DemoDataCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'inventory:demo
        {--clear : Remove the demo data instead of loading it}
        {--force : Skip the confirmation prompt}';

    /**
     * @var string
     */
    protected $description = 'ডেমো ডেটা যোগ করুন, অথবা আসল কাজ শুরুর আগে মুছে ফেলুন';

    public function handle(DemoDataService $demo): int
    {
        $admin = User::role(Role::SuperAdmin->value)->orderBy('id')->first();

        if ($admin === null) {
            $this->components->error('আগে একজন সুপার অ্যাডমিন তৈরি করুন (php artisan db:seed)।');

            return self::FAILURE;
        }

        $clearing = (bool) $this->option('clear');

        if (! $this->option('force') && ! $this->confirm($clearing
            ? 'এতে সব ডেমো পণ্য, স্টক, কাগজপত্র আর ডেমো কর্মী স্থায়ীভাবে মুছে যাবে। চালিয়ে যাবেন?'
            : 'ডেমো পণ্য, স্টক লেনদেন, রিটার্ন, ক্ষতি আর বদলির দাবি যোগ করবেন?')) {
            return self::SUCCESS;
        }

        try {
            if ($clearing) {
                $removed = $demo->clear($admin);
                $this->components->info('ডেমো ডেটা মুছে ফেলা হয়েছে। '.Bangla::number(array_sum($removed)).'টি রেকর্ড মোছা হয়েছে; ব্যবহারকারী, রোল, বৈশিষ্ট্য ও গুদাম রাখা হয়েছে।');
            } else {
                $demo->load($admin);
                $this->components->info('ডেমো ডেটা যোগ হয়েছে। ডেমো কর্মীদের পাসওয়ার্ড "password": '.implode(', ', DemoSeeder::USER_EMAILS));
            }
        } catch (ValidationException $exception) {
            $this->components->error(collect($exception->errors())->flatten()->first());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
