<?php
set_time_limit(10); // Set maximum execution time to 10 seconds

error_reporting(E_ERROR | E_PARSE); // Report simple running errors
error_reporting(0); // Turn off all error reporting

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tfdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

ini_set('memory_limit', '900M');

// Fetch POST data and validate it
$dataset = isset($_POST['dataset']) ? $_POST['dataset'] : '';
$geneName = isset($_POST['geneName']) ? $_POST['geneName'] : '';
$analysisType = isset($_POST['analysisType']) ? $_POST['analysisType'] : '';

if (empty($dataset) || empty($geneName) || empty($analysisType)) {
    die("All input fields are required.");
}

// Determine the tables based on cancer type
switch ($dataset) {
    case 'adeno':
        $rnaTable = 'adeno_rna_seq';
        $clinTable = 'adeno_clin';
        $mutTable = 'adeno_mutated';
        $columns = ['OS_MONTHS', 'OS_STATUS', 'DSS_MONTHS', 'DSS_STATUS', 'DFS_MONTHS', 'DFS_STATUS', 'PFS_MONTHS', 'PFS_STATUS'];
        break;
    case 'squam':
        $rnaTable = 'squam_rna_seq';
        $clinTable = 'squam_clin';
        $mutTable = 'squam_mutated';
        $columns = ['OS_MONTHS', 'OS_STATUS', 'DSS_MONTHS', 'DSS_STATUS', 'DFS_MONTHS', 'DFS_STATUS', 'PFS_MONTHS', 'PFS_STATUS'];
        break;
    case 'oncosg':
        $rnaTable = 'oncosg_rna_seq';
        $clinTable = 'oncosg_clin';
        $mutTable = 'oncosg_mutated';
        $columns = ['OS_MONTHS', 'OS_STATUS'];
        break;
	case 'mskcc':
#       $rnaTable = '?';
        $clinTable = 'mskcc_clin';
        $mutTable = 'mskcc_mutated';
        $columns = ['OS_MONTHS', 'OS_STATUS'];
		break;
	case 'mskcc2020':
#       $rnaTable = '?';
        $clinTable = 'mskcc2020_clin';
        $mutTable = 'mskcc2020_mutated';
        $columns = ['OS_MONTHS', 'OS_STATUS', 'RFS_MONTHS', 'RFS_STATUS'];
		break;
	case 'mskcc2022':
#       $rnaTable = '?';
        $clinTable = 'mskcc2022_clin';
        $mutTable = 'mskcc2022_mutated';
        $columns = ['OS_MONTHS', 'OS_STATUS'];
		break;
    default:
        die("Invalid cancer type specified.");
}

function fetchRows($conn, $query) {
    $result = $conn->query($query);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $result->free();
    return $rows;
}

if ($analysisType === 'expr') {
    // Fetch the row where the first column matches $geneName
    $queryRNA = "SELECT * FROM $rnaTable WHERE Hugo_Symbol = '$geneName'";
    $dataRNA = fetchRows($conn, $queryRNA);

    if (empty($dataRNA)) {
        die("Gene name $geneName not found in RNA data.");
    }

    // Extract the first row (only one row should match the gene name)
    $rowRNA = $dataRNA[0];

    // Remove the Hugo_Symbol column
    unset($rowRNA['Hugo_Symbol']);

    // Calculate the median of expression values
    $values = array_values($rowRNA);
    $medianValue = median($values);

    // Replace values with "High" or "Low"
    foreach ($rowRNA as $patientId => $value) {
        $rowRNA[$patientId] = ($value > $medianValue) ? 'High' : 'Low';
    }

    // Fetch the necessary columns from the clinical data
    $columnsStr = implode(', ', array_merge(['ids'], $columns));
    $queryClin = "SELECT $columnsStr FROM $clinTable";
    $dataClin = fetchRows($conn, $queryClin);

    // Prepare JSON strings without extra escaping
    $jsonRowRNA = json_encode($rowRNA, JSON_UNESCAPED_SLASHES);
    $jsonDataClin = json_encode($dataClin, JSON_UNESCAPED_SLASHES);

    // Paths to save the plots
    $plotFiles = [
        'OS' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'survival_plot_os.png'
    ];

    if ($dataset !== 'oncosg') {
        $plotFiles['DSS'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'survival_plot_dss.png';
        $plotFiles['DFS'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'survival_plot_dfs.png';
        $plotFiles['PFS'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'survival_plot_pfs.png';
    }

    // Delete the previous plot files if they exist
    foreach ($plotFiles as $plotFile) {
        if (file_exists($plotFile)) {
            unlink($plotFile);
        }
    }

    // Prepare Python script for survival analysis
    $pythonScript = "
import sys
sys.path.append('C:\\\\Users\\\\Guruguhan\\\\AppData\\\\Roaming\\\\Python\\\\Python312\\\\site-packages')
import json
import pandas as pd
from lifelines import KaplanMeierFitter
from lifelines.statistics import logrank_test
import matplotlib.pyplot as plt
import numpy as np

plt.switch_backend('Agg')

def perform_survival_analysis(gene_data, clin_data, gene_name, time_col, status_col, plot_title, plot_path):
    gene_data = json.loads(gene_data)
    clin_data = json.loads(clin_data)

    gene_df = pd.DataFrame(list(gene_data.items()), columns=['ids', 'expression'])
    gene_df['ids'] = gene_df['ids'].str.replace('-', '.').str[:12]  # Replace '-' with '.' in gene IDs and take first 12 characters
    clin_df = pd.DataFrame(clin_data)
    clin_df['ids'] = clin_df['ids'].str.replace('-', '.')  # Replace '-' with '.' in clinical IDs

    merged_data = pd.merge(gene_df, clin_df, on='ids')

    # Ensure data is numeric and drop NaNs in necessary columns
    merged_data[time_col] = pd.to_numeric(merged_data[time_col], errors='coerce')
    merged_data[status_col] = merged_data[status_col].apply(lambda x: 1 if '1' in str(x) else 0)
    merged_data.dropna(subset=[time_col, status_col], inplace=True)

    kmf = KaplanMeierFitter()
    fig, ax = plt.subplots()

    # Plot High and Low survival curves
    for label, color in zip(['High', 'Low'], ['red', 'blue']):
        mask = merged_data['expression'] == label
        if merged_data[mask].empty:
            print(f'No data for expression level {label}')
            continue
        n_samples = len(merged_data[mask])
        kmf.fit(merged_data[mask][time_col], event_observed=merged_data[mask][status_col].astype(int), label=f'{label} (n={n_samples})')
        kmf.plot_survival_function(ax=ax, ci_show=False, color=color)

    # Calculate p-value using log-rank test
    high_mask = merged_data['expression'] == 'High'
    low_mask = merged_data['expression'] == 'Low'
    results = logrank_test(merged_data[high_mask][time_col], merged_data[low_mask][time_col],
                           event_observed_A=merged_data[high_mask][status_col], event_observed_B=merged_data[low_mask][status_col])
    p_value = results.p_value

    plt.title(plot_title)
    plt.xlabel('Time (Months)')
    plt.ylabel('Survival Probability')
    plt.legend(title='Expression')
    plt.text(0.95, 0.05, f'p-value: {p_value:.3f}', verticalalignment='bottom', horizontalalignment='right', transform=ax.transAxes)
    plt.savefig(plot_path)
    plt.close()

    return plot_path

gene_data = '$jsonRowRNA'
clin_data = '$jsonDataClin'
gene_name = '$geneName'

plot_paths = [
    perform_survival_analysis(gene_data, clin_data, gene_name, 'OS_MONTHS', 'OS_STATUS', f'Survival Analysis for Gene: {gene_name} (OS)', r'{$plotFiles['OS']}')
]

if '$dataset' != 'oncosg':
    plot_paths.extend([
        perform_survival_analysis(gene_data, clin_data, gene_name, 'DSS_MONTHS', 'DSS_STATUS', f'Survival Analysis for Gene: {gene_name} (DSS)', r'{$plotFiles['DSS']}'),
        perform_survival_analysis(gene_data, clin_data, gene_name, 'DFS_MONTHS', 'DFS_STATUS', f'Survival Analysis for Gene: {gene_name} (DFS)', r'{$plotFiles['DFS']}'),
        perform_survival_analysis(gene_data, clin_data, gene_name, 'PFS_MONTHS', 'PFS_STATUS', f'Survival Analysis for Gene: {gene_name} (PFS)', r'{$plotFiles['PFS']}')
    ])
";

    // Write the Python script to a temporary file
    $scriptFile = tempnam(sys_get_temp_dir(), 'surv_analysis_') . '.py';
    file_put_contents($scriptFile, $pythonScript);

    // Execute the Python script and capture output
    $command = "python " . escapeshellarg($scriptFile);
    $output = shell_exec($command . ' 2>&1'); // Capture both stdout and stderr
    file_put_contents('python_output.log', $output); // Write the output to a log file

    echo "<div class='plot-container'>";
    foreach ($plotFiles as $type => $plotFile) {
        if (file_exists($plotFile)) {
            echo "<div class='plot'><h3>Survival Analysis Plot for $type</h3>";
            echo "<img src='data:image/png;base64," . base64_encode(file_get_contents($plotFile)) . "' alt='Survival Analysis Plot ($type)'></div>";
        }
    }
    echo "</div>";

    if ($dataset === 'oncosg') {
        echo "<p>Note: This dataset does not have information for DFS, DSS, or PFS.</p>";
    }

    // Clean up temporary file
    unlink($scriptFile);
}

if ($analysisType === 'mut') {
    // Fetch the sample IDs from the mutation table for the given gene
    $queryMut = "SELECT * FROM $mutTable WHERE Hugo_Symbol = '$geneName'";
    $dataMut = fetchRows($conn, $queryMut);

    $mutSamples = [];
    foreach ($dataMut as $sample) {
        $mutSamples[] = substr(str_replace('-', '.', $sample['Tumor_Sample_Barcode']), 0, 12);
    }

    // Create a row with "Mutated" or "Non-mutated"
    $rowMut = [];
    foreach ($mutSamples as $sample) {
        $rowMut[$sample] = 'Mutated';
    }

    // Get all unique samples from clinical data
    $queryClinSamples = "SELECT DISTINCT ids FROM $clinTable";
    $dataClinSamples = fetchRows($conn, $queryClinSamples);

    foreach ($dataClinSamples as $sample) {
        $id = str_replace('-', '.', $sample['ids']);
        if (!isset($rowMut[$id])) {
            $rowMut[$id] = 'Non-mutated';
        }
    }

    // Fetch the necessary columns from the clinical data
    $columnsStr = implode(', ', array_merge(['ids'], $columns));
    $queryClin = "SELECT $columnsStr FROM $clinTable";
    $dataClin = fetchRows($conn, $queryClin);

    // Prepare JSON strings without extra escaping
    $jsonRowMut = json_encode($rowMut, JSON_UNESCAPED_SLASHES);
    $jsonDataClin = json_encode($dataClin, JSON_UNESCAPED_SLASHES);

    // Paths to save the plots
    $plotFiles = [
        'OS' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'survival_plot_os.png'
    ];

    if ($dataset !== 'oncosg') {
        $plotFiles['DSS'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'survival_plot_dss.png';
        $plotFiles['DFS'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'survival_plot_dfs.png';
        $plotFiles['PFS'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'survival_plot_pfs.png';
    }
    
    if ($dataset === 'mskcc2020') {
        $plotFiles['RFS'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'survival_plot_rfs.png';
    }

    // Delete the previous plot files if they exist
    foreach ($plotFiles as $plotFile) {
        if (file_exists($plotFile)) {
            unlink($plotFile);
        }
    }

    // Prepare Python script for survival analysis
    $pythonScript = "
import sys
sys.path.append('C:\\\\Users\\\\Guruguhan\\\\AppData\\\\Roaming\\\\Python\\\\Python312\\\\site-packages')
import json
import pandas as pd
from lifelines import KaplanMeierFitter
from lifelines.statistics import logrank_test
import matplotlib.pyplot as plt
import numpy as np

plt.switch_backend('Agg')

def perform_survival_analysis(gene_data, clin_data, gene_name, time_col, status_col, plot_title, plot_path):
    gene_data = json.loads(gene_data)
    clin_data = json.loads(clin_data)

    gene_df = pd.DataFrame(list(gene_data.items()), columns=['ids', 'mutation'])
    gene_df['ids'] = gene_df['ids'].str.replace('-', '.').str[:12]  # Replace '-' with '.' in gene IDs and take first 12 characters
    clin_df = pd.DataFrame(clin_data)
    clin_df['ids'] = clin_df['ids'].str.replace('-', '.')  # Replace '-' with '.' in clinical IDs

    merged_data = pd.merge(gene_df, clin_df, on='ids')

    # Ensure data is numeric and drop NaNs in necessary columns
    merged_data[time_col] = pd.to_numeric(merged_data[time_col], errors='coerce')
    merged_data[status_col] = merged_data[status_col].apply(lambda x: 1 if '1' in str(x) else 0)
    merged_data.dropna(subset=[time_col, status_col], inplace=True)

    kmf = KaplanMeierFitter()
    fig, ax = plt.subplots()

    # Plot Mutated and Non-mutated survival curves
    for label, color in zip(['Mutated', 'Non-mutated'], ['red', 'blue']):
        mask = merged_data['mutation'] == label
        if merged_data[mask].empty:
            print(f'No data for mutation status {label}')
            continue
        n_samples = len(merged_data[mask])
        kmf.fit(merged_data[mask][time_col], event_observed=merged_data[mask][status_col].astype(int), label=f'{label} (n={n_samples})')
        kmf.plot_survival_function(ax=ax, ci_show=False, color=color)

    # Calculate p-value using log-rank test
    mutated_mask = merged_data['mutation'] == 'Mutated'
    non_mutated_mask = merged_data['mutation'] == 'Non-mutated'
    results = logrank_test(merged_data[mutated_mask][time_col], merged_data[non_mutated_mask][time_col],
                           event_observed_A=merged_data[mutated_mask][status_col], event_observed_B=merged_data[non_mutated_mask][status_col])
    p_value = results.p_value

    plt.title(plot_title)
    plt.xlabel('Time (Months)')
    plt.ylabel('Survival Probability')
    plt.legend(title='Mutation')
    plt.text(0.95, 0.05, f'p-value: {p_value:.3f}', verticalalignment='bottom', horizontalalignment='right', transform=ax.transAxes)
    plt.savefig(plot_path)
    plt.close()

    return plot_path

gene_data = '$jsonRowMut'
clin_data = '$jsonDataClin'
gene_name = '$geneName'

plot_paths = [
    perform_survival_analysis(gene_data, clin_data, gene_name, 'OS_MONTHS', 'OS_STATUS', f'Survival Analysis for Gene: {gene_name} (OS)', r'{$plotFiles['OS']}')
]

if '$dataset' == 'mskcc2020':
    plot_paths.append([
        perform_survival_analysis(gene_data, clin_data, gene_name, 'RFS_MONTHS', 'RFS_STATUS', f'Survival Analysis for Gene: {gene_name} (RFS)', r'{$plotFiles['RFS']}')
    ])
	
if '$dataset' != 'oncosg':
    plot_paths.extend([
        perform_survival_analysis(gene_data, clin_data, gene_name, 'DSS_MONTHS', 'DSS_STATUS', f'Survival Analysis for Gene: {gene_name} (DSS)', r'{$plotFiles['DSS']}'),
        perform_survival_analysis(gene_data, clin_data, gene_name, 'DFS_MONTHS', 'DFS_STATUS', f'Survival Analysis for Gene: {gene_name} (DFS)', r'{$plotFiles['DFS']}'),
        perform_survival_analysis(gene_data, clin_data, gene_name, 'PFS_MONTHS', 'PFS_STATUS', f'Survival Analysis for Gene: {gene_name} (PFS)', r'{$plotFiles['PFS']}')
    ])
";

    // Write the Python script to a temporary file
    $scriptFile = tempnam(sys_get_temp_dir(), 'surv_analysis_') . '.py';
    file_put_contents($scriptFile, $pythonScript);

    // Execute the Python script and capture output
    $command = "python " . escapeshellarg($scriptFile);
    $output = shell_exec($command . ' 2>&1'); // Capture both stdout and stderr
    file_put_contents('python_output.log', $output); // Write the output to a log file

    echo "<div class='plot-container'>";
    foreach ($plotFiles as $type => $plotFile) {
        if (file_exists($plotFile)) {
            echo "<div class='plot'><h3>Survival Analysis Plot for $type</h3>";
            echo "<img src='data:image/png;base64," . base64_encode(file_get_contents($plotFile)) . "' alt='Survival Analysis Plot ($type)'></div>";
        }
    }
    echo "</div>";

    if ($dataset === 'oncosg') {
        echo "<p>Note: This dataset does not have information for DFS, DSS, or PFS.</p>";
    }

    // Clean up temporary file
    unlink($scriptFile);
}

$conn->close();

function median($values) {
    sort($values);
    $count = count($values);
    $middle = floor(($count - 1) / 2);
    if ($count % 2) {
        return $values[$middle];
    } else {
        return ($values[$middle] + $values[$middle + 1]) / 2.0;
    }
}
?>