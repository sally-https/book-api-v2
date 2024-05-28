<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToStudentsTable extends Migration
{
    public function up()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('student_id')->unique()->after('name');
            $table->string('email')->unique()->after('student_id');
            $table->string('password')->after('email');
            $table->rememberToken()->after('password');
        });
    }

    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('student_id');
            $table->dropColumn('email');
            $table->dropColumn('password');
            $table->dropRememberToken();
        });
    }
}
