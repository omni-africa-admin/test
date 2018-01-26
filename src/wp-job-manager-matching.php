<?php
/**
 * Establish a connection to the database
 * @return \PDO
 */
function init_db_connector () {
    try
    {
        $bdd = new PDO('mysql:host=localhost;dbname=webdb;charset=utf8', 'webuser', 'password');
    }
    catch (Exception $e)
    {
        die('Erreur : ' . $e->getMessage());
    }
    return $bdd;
}

/**
 * Calcul the distance between two places
 * @param string $locationA
 * @param string $locationB
 * @return int
 */
function distance($locationA, $locationB)
{
    if (strcmp($locationA, $locationB) == 0)
        return 1;
    else
        return 0;
}

/**
 * Calcul the percentage matching of one job for all candidate
 * Insert the percentage matching in the database
 * @param array $job
 */
function insert_candidate_matching ($job) {
    $bdd=init_db_connector();
    $reponse = $bdd->query("SELECT * FROM wp_bindu_candidat");
    if ($reponse->rowCount() > 0)
    {        
        while ($donnees = $reponse->fetch())
        {   
           $result=matching ($donnees, $job);
           save_percentage ($job['post_name'], $donnees['candidateId'], $result);
        }
    }
}
/**
 * Calcul the matching percentage and return the result
 * @param   array   $candidate
 * @param   array   $job
 * @return  float     $matching_percentage
 */
function matching($candidate, $job)
{
    $job ['job_experience']=0;
    
    $matching_process=new WP_Job_Manager_Matching();
    $candidate_skills = array($candidate['candidateCompetence1'], $candidate['candidateCompetence2'], $candidate['candidateCompetence3']);
    $needed_skills = array($job['job_competence']);

    $skills = skills($needed_skills, $candidate_skills);
    
    if (((int)$candidate['candidateAge'] >= (int)$job ['job_age_min']) && 
        ((int)$candidate['candidateAge'] <= (int)$job ['job_age_max']) && 
        (strcmp($job ['job_domain'], $candidate['candidateDomain']) == 0))
    {
        $indice_candidate_diploma = array_search ($candidate['candidateLevel'], $matching_process->get_diploma());
        $indice_job_diploma = array_search ($job ['job_diploma'], $matching_process->get_diploma());

        foreach ($matching_process->get_criteria() as $key => $value)
        {
            $percentage = $matching_process->get_percentage_max();
            $compteur = 0;
            
            while ($matching_process->get_criteria()[$key] < 0)
            {
                switch ($key) 
                {
                    case 'distance' :
                        if (distance($job ['job_location'], $candidate['candidateLocation']) == 1)
                        {  
                            $matching_process->set_criteria('distance', $percentage);
                        } 
                        else
                        {
                            $matching_process->set_criteria('distance', 0);
                        }
                        break;
                    case 'experience' : 
                        {
                            if (($job ['job_experience'] - $candidate['candidateExperience'] == $compteur) || ($candidate['candidateExperience'] > $job ['job_experience']))
                            {
                                $matching_process->set_criteria('experience', $percentage);
                            }
                            break;
                        }
                    case 'education' : 
                        {
                            if (($indice_job_diploma - $indice_candidate_diploma == $compteur) || ($indice_candidate_diploma > $indice_job_diploma))
                            {
                                $matching_process->set_criteria('education', $percentage);
                            }
                            break;
                        }
                    case 'competence' : 
                        {
                            if ($skills==0)
                            {
                                $matching_process->set_criteria('competence', 0);
                                break;
                            }
                            else if ((count($needed_skills)) - $skills == $compteur)
                            {
                                $matching_process->set_criteria('competence', $percentage);
                                break; 
                            }                         
                        }
                }
                $compteur++;
                $percentage -= $matching_process->get_recruiter_info()['step'];
                if ($percentage<=0)
                {
                    $matching_process->set_criteria($key, 0);
                }
            }
        }
    } 
    else
    {
        foreach ($matching_process->get_criteria() as $key => $value)
        {
            $matching_process->set_criteria($key, 0);
        }
    }
    //---------------------------------------------------------------------------------------------
    //Calcul du resultat de matching
    //---------------------------------------------------------------------------------------------
    $matching_percentage = (($matching_process->get_criteria()['distance'] * $matching_process->get_recruiter_info()['distance']) / $matching_process->get_percentage_max()) + (($matching_process->get_criteria()['education'] * $matching_process->get_recruiter_info()['education']) / $matching_process->get_percentage_max()) + (($matching_process->get_criteria()['experience'] * $matching_process->get_recruiter_info()['experience']) / $matching_process->get_percentage_max()) + (($matching_process->get_criteria()['competence'] * $matching_process->get_recruiter_info()['competence']) / $matching_process->get_percentage_max());
    return $matching_percentage;
}


/**
 * Counts the number of skills of the candidate among those required by the job
 * @param   array   $needed_skills
 * @param   array   $candidate_skills
 * @return  int     $skills_number
 */
function skills($needed_skills, $candidate_skills)
{
    $skills_number = 0;
    for ($i = 0; $i < count($needed_skills); $i++)
    {
        for ($j = 0; $j < count($candidate_skills); $j++)
        {
            if (strcmp($needed_skills[$i], $candidate_skills[$j]) == 0)
            {
                $skills_number += 1;
            }
        }
    }
    return $skills_number;
}

/**
 * Insert the matching percentage in database
 * @param   string  $jobname
 * @param   int     $candidateID
 * @param   float   $matchingPercentage
 */
function save_percentage ($jobname, $candidateID, $matchingPercentage) {
    $bdd=init_db_connector();
    $bdd->query("INSERT INTO wp_bindu_matching (user_id, post_name, value) VALUES ('$candidateID', '$jobname', '$matchingPercentage')");
}

/**
 * Calcul the percentage matching of one candidate for all job
 * Insert the percentage matching in the database
 * @param   array   $candidate
 */
function insert_job_matching ($candidate) {
    $allJob=get_job_listings()->posts;
    for ($job=0;$job<count($allJob);$job++) {
        $matchingPercentage=matching($candidate, get_object_vars($allJob[$job]));
        save_percentage ($allJob[$job]->post_name, $candidate['candidateID'], $matchingPercentage);
    }
}
/**
 * Calcul the percentage matching of one candidate for all job
 * Update the percentage matching in the database
 * @param   array   $candidate
 */
function update_job_matching ($candidate) {
        $allJob=get_job_listings()->posts;
        for ($job=0;$job<count($allJob);$job++) {
            $matchingPercentage=matching($candidate, get_object_vars($allJob[$job]));
            update_percentage ($allJob[$job]->post_name, $candidate['candidateID'], $matchingPercentage);
        }
}

/**
 * Calcul the percentage matching of one job for all candidate
 * Update the percentage matching in the database
 * @param array $job
 */
function update_candidate_matching ($job)
{
    $bdd=init_db_connector();
    $reponse = $bdd->query("SELECT * FROM wp_bindu_candidat");
    if ($reponse->rowCount() > 0)
    {
        $competence_job = array($job['job_competence']);
        while ($donnees = $reponse->fetch())
        {
            $result = matching ($donnees, $job);
            update_job_percentage ($job['ID'], $donnees['candidateId'], $result);
        }
    }
}
/**
 * 
 * @param   int     $jobID
 * @param   int     $candidateID
 * @param   float   $matchingPercentage
 */
function update_job_percentage ($jobID, $candidateID, $matchingPercentage)
{
    $bdd=init_db_connector();
    $reponse = $bdd->query("SELECT post_name FROM wp_bindu_posts WHERE ID =" . $jobID . "");
    if ($reponse->rowCount() > 0)
    {
        while ($donnees = $reponse->fetch())
        {
            $jobname = $donnees['post_name'];
            $reponse2 = $bdd->query("SELECT ID FROM wp_bindu_matching WHERE user_id =" . $candidateID . " AND post_name='" . $jobname . "'");
            if ($reponse2->rowCount() > 0)
            {

                while ($donnees2 = $reponse2->fetch())
                {
                    $bdd->query("UPDATE wp_bindu_matching SET value=" . $matchingPercentage . " WHERE ID=" . $donnees2['ID'] . "");
                }
            }
        }
    }
}
/**
 * Update the percentage matching in the database
 * @param   string  $jobname
 * @param   int     $candidateID
 * @param   float   $matchingPercentage
 */
function update_percentage ($jobname, $candidateID, $matchingPercentage) {
    $bdd=init_db_connector();
    $reponse = $bdd->query("SELECT ID FROM wp_bindu_matching WHERE user_id =".$candidateID." AND post_name='".$jobname."'");
    if ($reponse->rowCount() > 0)
    {
        $donnees = $reponse->fetch();
        $bdd->query("UPDATE wp_bindu_matching SET value=".$matchingPercentage." WHERE ID=".$donnees['ID']."");
    }
}

/**
 * Delete all matching percentage for one job
 * @param int $job_id
 */
function delete_job_matching ($job_id) {
    $bdd=init_db_connector();
    $reponse = $bdd->query("SELECT post_name FROM wp_bindu_posts WHERE ID =" . $job_id . "");
    if ($reponse->rowCount() > 0)
    {
        while ($donnees = $reponse->fetch())
        {
            $jobname = $donnees['post_name'];
            $bdd->query("DELETE FROM wp_bindu_matching WHERE post_name='".$jobname."'");
        }
    }

}

/*
 * Delete all matching percentage for one candidate
 * @param int $candidate_id
 */
function delete_candidate_matching ($candidate_id) {
    $bdd=init_db_connector();
    $bdd->query("DELETE FROM wp_bindu_matching WHERE user_id=".$candidate_id);
}


/**
 * Delete one candidate profile
 * @param int $candidate_id
 */
function delete_candidate_profile ($candidate_id) {
    $bdd=init_db_connector();
    $bdd->query("DELETE FROM wp_bindu_candidat WHERE candidateId=".$candidate_id);
}

