<?php

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
  $family_history = isset($_POST['family_history']) ? (array)$_POST['family_history'] : [];

        // Psychiatric illnesses (checkbox group)
        if (in_array('psychiatric', $family_history) && isset($_POST['psychiatric'])) {
            $psychiatricIllnesses = array_map('sanitizeInput', (array)$_POST['psychiatric']);
            $family_history = array_diff($family_history, ['family_history_psychiatric']);
            $family_history = array_merge($family_history, $psychiatricIllnesses);

         
        }


         

        // Other mental illness (free text)
        if (in_array('psychiatric', $family_history) && !empty($_POST['family_other_mental_illness'])) {
            $psychiatric_illnesses_details_family = 'Other Mental Illness (Family): ' . sanitizeInput($_POST['family_other_mental_illness']);
            $family_history = array_diff($family_history, ['family_other_mental_illness']);
            $family_history[] = $psychiatric_illnesses_details_family;
            echo"yes";
        }

        // Cancer with details
        if (in_array('cancer_specify_family', $family_history) && !empty($_POST['family_cancer_details'])) {
            $cancer_details = 'Cancer: ' . sanitizeInput($_POST['family_cancer_details']);
            $family_history = array_diff($family_history, ['cancer_specify_family']);
            $family_history[] = $cancer_details;
        }

        // Food allergies with details
        if (in_array('familyFoodAllergies', $family_history) && !empty($_POST['allergiesSpecifyFamily'])) {
            $allergySpecFamily = 'Family Allergies: ' . sanitizeInput($_POST['allergiesSpecifyFamily']);
            $family_history = array_diff($family_history, ['familyFoodAllergies']);
            $family_history[] = $allergySpecFamily;
        }

        // Other illness (free text)
        if (in_array('other', $family_history) && !empty($_POST['otherIllness'])) {
            $otherIllness = 'Other (Family): ' . sanitizeInput($_POST['otherIllness']);
            $family_history = array_diff($family_history, ['other']);
            $family_history[] = $otherIllness;
        }

// Debug output
echo "<pre>";
print_r($family_history);
echo "</pre>";

?>

<form action="tester.php" method="POST">
   <div class="form-step" id="step3" >
                <fieldset class="form-section">
                    <legend>Past Medical & Surgical History</legend>
                    <label class="form-label">Which of these conditions have you had in the past?</label>
                    <div class="checkbox-grid">
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="varicella">
                                Varicella (Chicken Pox)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="dengue">
                                Dengue
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="tuberculosis">
                                Tuberculosis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="pneumonia">
                                Pneumonia
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="uti">
                                Urinary Tract Infection
                            </label>
                        </div>
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="appendicitis">
                                Appendicitis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="cholecystitis">
                                Cholecystitis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="measles">
                                Measles
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="typhoid fever">
                                Typhoid Fever
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="amoebiasis">
                                Amoebiasis
                            </label>
                        </div>
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="past_illness[]" value="kidney stones">
                                Kidney Stones
                            </label>

                            <div class="checkbox-column">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="past_illness[]" value="injury">
                                    Injury
                                    <div class="nested-checkboxes">
                                        <label class="checkbox-label nested">
                                            <input type="checkbox" name="past_illness[]" value="burn">
                                            Burn
                                        </label>
                                        <label class="checkbox-label nested">
                                            <input type="checkbox" name="past_illness[]" value="stab">
                                            Stab/Laceration
                                        </label>
                                        <label class="checkbox-label nested">
                                            <input type="checkbox" name="past_illness[]" value="fracture">
                                            Fracture
                                        </label>
                                    </div>
                                </label>



                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="otherPastIllnessCheckbox" onclick="toggleOtherPastIllness()">
                            Other (Specify)
                        </label>
                        <input type="text" class="form-control" id="otherPastIllnessInput" name="past_illness_other" placeholder="Specify other illnesses" style="display: none; width: 300px; margin-top: 5px;">
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Hospital Admission / Surgery</legend>
                    <label class="form-label">Have you ever been admitted to the hospital and/or undergone surgery?</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="hospital_admission" value="No" checked onclick="toggleSurgeryFields(false)">
                            No
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="hospital_admission" value="Yes" onclick="toggleSurgeryFields(true)">
                            Yes
                        </label>
                    </div>

                    <div id="surgeryDetails" style="display: none; margin-top: 15px;">
                        <table class="medications-table" id="surgeryTable">
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Reason</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="number" class="table-input" name="hospital_admissions[0][year]" min="1900" max="2025" placeholder="e.g., 2015">
                                    </td>
                                    <td>
                                        <input type="text" class="table-input" name="hospital_admissions[0][reason]" placeholder="e.g., Appendectomy">
                                    </td>
                                    <td>
                                        <button type="button" class="remove-btn" onclick="removeSurgeryRow(this)">×</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="add-btn btn btn-secondary" onclick="addSurgeryRow()">+ Add Admission/Surgery</button>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Family Medical History</legend>
                    <label class="form-label">Indicate the known health conditions of your immediate family members:</label>
                    <div class="checkbox-grid">
                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="asthma">
                                Bronchial Asthma ("Hika")
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="familyFoodAllergies">
                                Food Allergies
                                <input type="text" placeholder="Specify food" name="allergiesSpecifyFamily" class="inline-input">
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="rhinitis">
                                Allergic Rhinitis
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="hyperthyroidism">
                                Hyperthyroidism
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="hypothyroidism">
                                Hypothyroidism/Goiter
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="anemia">
                                Anemia
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="migraine">
                                Migraine (recurrent headaches)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="epilepsy">
                                Epilepsy/Seizures
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="gerd">
                                Gastroesophageal Reflux Disease (GERD)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="bowel_syndrome">
                                Irritable Bowel Syndrome
                            </label>
                        </div>

                        <div class="checkbox-column">
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="psychiatric">
                                Psychiatric Illness:
                                <div class="nested-checkboxes">
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="depression">
                                        Major Depressive Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="bipolar">
                                        Bipolar Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="anxiety">
                                        Generalized Anxiety Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="panic">
                                        Panic Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="ptsd">
                                        Posttraumatic Stress Disorder
                                    </label>
                                    <label class="checkbox-label nested">
                                        <input type="checkbox" name="family_history[]" value="schizophrenia">
                                        Schizophrenia
                                    </label>

                                    Other:
                                    <input type="text" placeholder="Specify other mental illness" name="family_other_mental_illness" class="inline-input">
                                </div>
                            </label>

                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="lupus">
                                Systemic Lupus Erythematosus (SLE)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="hypertension">
                                Hypertension (elevated blood pressure)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="diabetes">
                                Diabetes mellitus (elevated blood sugar)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="dyslipidemia">
                                Dyslipidemia (elevated cholesterol levels)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="arthritis">
                                Arthritis (joint pains)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="sle">
                                Systemic Lupus Erythematosus (SLE)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="pcos">
                                Polycystic Ovarian Syndrome (PCOS)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="cancer_specify_family" onchange="toggleExtraFamilyIllness(this, 'cancer-input-family')">
                                Cancer
                            </label>

                            <div id="cancer-input-family" class="extra-input">
                                <input type="text" name="family_cancer_details" placeholder="Please specify..." />
                            </div>

                            <br>
                            <label class="checkbox-label">
                                <input type="checkbox" name="family_history[]" value="other">
                                Other:
                                <input type="text" placeholder="Specify if there is" name="otherIllness" class="inline-input">
                            </label>
                        </div>
                    </div>
                </fieldset>

                <script>
                    function toggleExtraFamilyIllness(checkbox, inputId) {
                        let extra = document.getElementById(inputId);
                        if (checkbox.checked) {
                            extra.style.display = "block";
                        } else {
                            extra.style.display = "none";
                        }
                    }
                </script>

            </div>
        </fieldset>

       
        <input type="submit" value="Submit">
</form>