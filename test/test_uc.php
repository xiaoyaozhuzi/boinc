#! /usr/local/bin/php
<?php
    // This tests whether the most basic mechanisms are working
    // Also whether stderr output is reported correctly
    // Also tests if water levels are working correctly

    include_once("test.inc");

    $retval = 0;

    $project = new Project;

    // the following is optional (makes client web download possible)
    $core_version = new Core_Version($core_app);
    $project->add_core_version($core_version);

    $app = new App("upper_case");
    $app_version = new App_Version($app);
    $project->add_app($app);
    $project->add_app_version($app_version);

    $user = new User();
    $user->project_prefs = "<project_specific>\nfoobar\n</project_specific>\n";
    $user->global_prefs = "<venue name=\"home\">\n".
    "<work_buf_min_days>0</work_buf_min_days>\n".
    "<work_buf_max_days>2</work_buf_max_days>\n".
    "<disk_interval>1</disk_interval>\n".
    "<run_on_batteries/>\n".
    "<max_bytes_sec_down>400000</max_bytes_sec_down>\n".
    "</venue>\n";

    $project->add_user($user);
    $project->install();      // must install projects before adding to hosts
    $project->install_feeder();

    $host = new Host();
    $host->log_flags = "log_flags.xml";
    $host->add_user($user, $project);
    $host->install();

    echo "adding work\n";

    $work = new Work($app);
    $work->wu_template = "uc_wu";
    $work->result_template = "uc_result";
    $work->redundancy = 2;
    $work->delay_bound = 2;
    // Say that 1 WU takes 1 day on a ref comp
    $work->rsc_fpops = 86400*1e9/2;
    $work->rsc_iops = 86400*1e9/2;
    $work->rsc_disk = 10e8;
    array_push($work->input_files, "input");
    $work->install($project);

    $project->start_servers();
    sleep(1);       // make sure feeder has a chance to run
    $host->run("-exit_when_idle -skip_cpu_benchmarks");

    $project->stop();
    $project->restart();
    $project->validate($app, 2);
    $result->server_state = RESULT_STATE_OVER;
    $result->stderr_out = "APP: upper_case: starting, argc 1";
    $result->exit_status = 0;
    $project->check_results(2, $result);
    $project->compare_file("uc_wu_0_0", "uc_correct_output");
    $project->compare_file("uc_wu_1_0", "uc_correct_output");

    $project->assimilate($app);
    $project->file_delete();
    if (file_exists("$project->project_dir/download/input")) {
        echo "ERROR: File $project->project_dir/download/input still there\n";
        $retval = -1;
    }
    if (file_exists("$project->project_dir/upload/uc_wu_0_0")) {
        echo "ERROR: File $project->project_dir/upload/uc_wu_0_0 still there\n";
        $retval = -1;
    }
    if (file_exists("$project->project_dir/upload/uc_wu_1_0")) {
        echo "ERROR: File $project->project_dir/upload/uc_wu_1_0 still there\n";
        $retval = -1;
    }
    $project->stop();

    exit($retval);
?>
