<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");
$page_title = "Alumni Records";
$active_page = "alumni_management";

// Fetch all alumni data
$query = "
    SELECT ap.user_id AS alumni_id, ap.first_name, ap.middle_name, ap.last_name, ap.employment_status AS job, ap.year_graduated AS batch, u.email,
           COUNT(ad.doc_id) AS total_docs,
           SUM(CASE WHEN ad.document_status = 'Pending' THEN 1 ELSE 0 END) AS pending_docs,
           SUM(CASE WHEN ad.document_status = 'Approved' THEN 1 ELSE 0 END) AS approved_docs,
           SUM(CASE WHEN ad.document_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_docs
    FROM alumni_profile ap
    LEFT JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN alumni_documents ad ON ap.user_id = ad.user_id
    WHERE ap.year_graduated IS NOT NULL
    GROUP BY ap.user_id
";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$alumni = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
    $row['requirement_status'] = 'None';
    if ($row['pending_docs'] > 0) {
        $row['requirement_status'] = 'Pending';
    } elseif ($row['rejected_docs'] > 0) {
        $row['requirement_status'] = 'Rejected';
    } elseif ($row['approved_docs'] == $row['total_docs'] && $row['total_docs'] > 0) {
        $row['requirement_status'] = 'Approved';
    }
    $alumni[] = $row;
}

ob_start();
?>
<!-- Control Panel: Search and Filter -->
<div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover mb-6 w-full"> 
    <div class="flex flex-col sm:flex-row gap-4 items-center justify-between"> 
        <!-- Search Bar -->
        <div class="relative w-full sm:w-2/3"> 
            <input type="text" id="alumniSearchInput" placeholder="Search by name..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
        </div>
        
        <!-- Batch Filter Wrapper (Visible in Overview) -->
        <div id="batchFilterWrapper" class="w-full sm:w-1/3">
            <select id="batchFilterDropdown" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                <!-- Options populated by JS -->
            </select>
        </div>
        
        <!-- Employment Status Filter Wrapper (Hidden initially, visible in Detail) -->
        <div id="employmentFilterWrapper" class="w-full sm:w-1/3 hidden">
            <select id="employmentFilterDropdown" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                <!-- Options populated by JS -->
            </select>
        </div>
    </div>
</div>

<!-- Batch Overview Section (Initial view) -->
<div id="batchOverviewContainer" class="mt-8 mb-6">
    <!-- Content injected by JS -->
</div>

<!-- Alumni Records Detail Section (Hidden by default) -->
<div id="alumniBatchDetailContainer" class="hidden bg-white p-6 rounded-xl shadow-lg stats-card card-hover overflow-x-auto">
    <!-- Back Button and Dynamic Title -->
    <div id="batchDetailHeader" class="flex items-center justify-between mb-4 border-b pb-2">
        <button id="backToOverviewBtn" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 font-semibold text-sm transition shadow">
            Back to Overview
        </button>
        <h3 id="batchDetailTitle" class="text-2xl font-bold text-gray-800"></h3>
    </div>
    <div id="alumniTableContainer">
        <!-- Table injected by JS -->
    </div>
</div>

<!-- Documents Modal -->
<div id="docsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white p-6 rounded-xl shadow-lg max-w-4xl w-full">
        <h2 class="text-xl font-bold mb-4">Alumni Documents</h2>
        <div id="docsContent" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200"></tbody>
            </table>
        </div>
        <button onclick="closeDocsModal()" class="mt-4 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">Close</button>
    </div>
</div>

<script>
const alumniData = <?php echo json_encode($alumni); ?>;
let currentViewedBatch = null;

function viewBatchRecords(batch) {
    currentViewedBatch = batch;
    const overviewContainer = document.getElementById('batchOverviewContainer');
    const detailContainer = document.getElementById('alumniBatchDetailContainer');
    if (overviewContainer) overviewContainer.classList.add('hidden');
    if (detailContainer) detailContainer.classList.remove('hidden');
    document.getElementById('batchFilterWrapper').classList.add('hidden');
    document.getElementById('employmentFilterWrapper').classList.remove('hidden');
    document.getElementById('employmentFilterDropdown').value = 'all';
    document.getElementById('alumniSearchInput').value = '';
    document.getElementById('batchDetailTitle').textContent = `Alumni Records: Batch ${batch}`;
    renderAlumniDetailTable();
}

function showBatchOverview() {
    currentViewedBatch = null;
    const overviewContainer = document.getElementById('batchOverviewContainer');
    const detailContainer = document.getElementById('alumniBatchDetailContainer');
    if (detailContainer) detailContainer.classList.add('hidden');
    if (overviewContainer) overviewContainer.classList.remove('hidden');
    document.getElementById('employmentFilterWrapper').classList.add('hidden');
    document.getElementById('batchFilterWrapper').classList.remove('hidden');
    renderBatchOverview();
}

function renderBatchOverview() {
    const container = document.getElementById('batchOverviewContainer');
    if (!container) return;
    const searchTerm = document.getElementById('alumniSearchInput').value.toLowerCase().trim();
    const batchFilterValue = document.getElementById('batchFilterDropdown')?.value || 'all';
    let dataToProcess = alumniData;
    if (searchTerm) {
        dataToProcess = dataToProcess.filter(a => a.full_name.toLowerCase().includes(searchTerm));
    }
    const groupedByBatch = dataToProcess.reduce((acc, alumni) => {
        const batch = alumni.batch;
        if (!acc[batch]) {
            acc[batch] = { count: 0, employed: 0, unemployed: 0 };
        }
        acc[batch].count++;
        if (['Employed', 'Self-Employed', 'Employed & Student'].includes(alumni.job)) {
            acc[batch].employed++;
        } else if (['Unemployed', 'Student'].includes(alumni.job)) {
            acc[batch].unemployed++;
        }
        return acc;
    }, {});
    let sortedBatches = Object.keys(groupedByBatch).sort((a, b) => b - a);
    if (batchFilterValue !== 'all') {
        sortedBatches = sortedBatches.filter(batch => String(batch) === batchFilterValue);
    }
    let htmlContent = `<h3 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Batch Overview</h3>`;
    htmlContent += `<div class="flex flex-nowrap overflow-x-auto space-x-4 pb-4 -mx-2 px-2">`;
    if (sortedBatches.length === 0) {
        htmlContent += `<p class="p-4 text-gray-500">No batches found matching the current search or filter criteria.</p>`;
    }
    sortedBatches.forEach(batch => {
        const stats = groupedByBatch[batch];
        htmlContent += `
            <div class="batch-card flex-shrink-0 w-64 bg-white p-6 rounded-xl shadow-lg stats-card card-hover border-t-4 border-blue-600 hover:shadow-xl transition cursor-pointer" onclick="viewBatchRecords('${batch}')">
                <h4 class="text-4xl font-extrabold text-gray-900 mt-1 mb-3">Batch ${batch}</h4>
                <div class="space-y-1 text-sm">
                    <p class="text-gray-700 font-medium">Total Alumni: <span class="text-blue-600 font-bold">${stats.count}</span></p>
                    <p class="text-gray-700 font-medium">Employed (Total): <span class="text-green-600 font-bold">${stats.employed}</span></p>
                    <p class="text-gray-700 font-medium">Unemployed: <span class="text-red-600 font-bold">${stats.unemployed}</span></p>
                </div>
            </div>
        `;
    });
    htmlContent += `</div>`;
    container.innerHTML = htmlContent;
}

function renderAlumniDetailTable() {
    const container = document.getElementById('alumniTableContainer');
    if (!container) return;
    let html = '<table class="min-w-full divide-y divide-gray-200">';
    html += '<thead><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th><th>Batch Year</th><th>Employment Status</th><th>Requirement Status</th><th>Actions</th></tr></thead>';
    html += '<tbody class="bg-white divide-y divide-gray-200">';
    const searchTerm = document.getElementById('alumniSearchInput').value.toLowerCase().trim();
    const filterValue = document.getElementById('employmentFilterDropdown').value;
    const filteredAlumni = alumniData.filter(alumni => {
        if (currentViewedBatch && alumni.batch != currentViewedBatch) return false;
        if (searchTerm && !alumni.full_name.toLowerCase().includes(searchTerm)) return false;
        if (filterValue !== 'all' && alumni.job !== filterValue) return false;
        return true;
    });
    if (filteredAlumni.length === 0) {
        html += '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No alumni found matching the criteria.</td></tr>';
    } else {
        filteredAlumni.forEach(alumni => {
            html += `<tr>
                <td class="px-6 py-4 whitespace-nowrap">${alumni.full_name}</td>
                <td class="px-6 py-4 whitespace-nowrap">${alumni.batch}</td>
                <td class="px-6 py-4 whitespace-nowrap">${alumni.job || 'Unknown'}</td>
                <td class="px-6 py-4 whitespace-nowrap">${alumni.requirement_status}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <a href="edit_alumni.php?user_id=${alumni.alumni_id}" class="bg-blue-500 text-white py-1 px-3 rounded hover:bg-blue-600 text-sm mr-2">Edit</a>
                    <button onclick="showDocs(${alumni.alumni_id})" class="bg-green-500 text-white py-1 px-3 rounded hover:bg-green-600 text-sm">View Documents</button>
                </td>
            </tr>`;
        });
    }
    html += '</tbody></table>';
    container.innerHTML = html;
}

function setupBatchFilter() {
    const dropdown = document.getElementById('batchFilterDropdown');
    if (!dropdown) return;
    const uniqueBatches = [...new Set(alumniData.map(a => a.batch))].sort((a, b) => b - a);
    let optionsHtml = '<option value="all">Filter by Batch Year (All)</option>';
    uniqueBatches.forEach(batch => {
        optionsHtml += `<option value="${batch}">Batch ${batch}</option>`;
    });
    dropdown.innerHTML = optionsHtml;
    dropdown.addEventListener('change', renderBatchOverview);
}

function setupEmploymentFilter() {
    const dropdown = document.getElementById('employmentFilterDropdown');
    if (!dropdown) return;
    const statusOptions = [
        { value: 'all', label: 'Filter by Employment Status (All)' },
        { value: 'Employed', label: 'Employed' },
        { value: 'Self-Employed', label: 'Self-Employed' },
        { value: 'Unemployed', label: 'Unemployed' },
        { value: 'Student', label: 'Student' },
        { value: 'Employed & Student', label: 'Employed & Student' }
    ];
    let optionsHtml = '';
    statusOptions.forEach(option => {
        optionsHtml += `<option value="${option.value}">${option.label}</option>`;
    });
    dropdown.innerHTML = optionsHtml;
    dropdown.addEventListener('change', renderAlumniDetailTable);
}

function showDocs(userId) {
    const alumni = alumniData.find(a => a.alumni_id == userId);
    if (!alumni) return;
    const modal = document.getElementById("docsModal");
    const content = document.getElementById("docsContent").querySelector("tbody");
    fetch(`get_documents.php?user_id=${userId}`)
        .then(response => response.json())
        .then(docs => {
            content.innerHTML = docs.map(doc => `
                <tr>
                    <td class="p-2">${doc.document_type}</td>
                    <td class="p-2">${doc.file_path}</td>
                    <td class="p-2">${doc.document_status}</td>
                    <td class="p-2">
                        <select onchange="updateStatus(${doc.doc_id}, this.value, '${encodeURIComponent(doc.document_type)}', ${userId}, this)">
                            <option value="Pending" ${doc.document_status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Approved" ${doc.document_status === 'Approved' ? 'selected' : ''}>Approved</option>
                            <option value="Rejected" ${doc.document_status === 'Rejected' ? 'selected' : ''}>Rejected</option>
                        </select>
                        <input type="text" id="reason_${doc.doc_id}" placeholder="Enter rejection reason" value="${doc.rejection_reason || ''}" class="border rounded-lg p-1 mt-2 w-full ${doc.document_status === 'Rejected' ? '' : 'hidden'}">
                    </td>
                </tr>
            `).join('');
            modal.classList.remove("hidden");
        })
        .catch(error => console.error("Fetch error:", error));
}

function closeDocsModal() {
    document.getElementById("docsModal").classList.add("hidden");
}

function updateStatus(docId, status, docType, alumniId, selectElement) {
    const reasonInput = document.getElementById(`reason_${docId}`);
    const reason = status === 'Rejected' ? (reasonInput.value || 'No reason provided') : '';
    const reasonInputs = document.querySelectorAll(`#docsModal input[id^="reason_"]`);
    reasonInputs.forEach(input => {
        input.classList.add('hidden');
        if (input.id === `reason_${docId}` && status === 'Rejected') {
            input.classList.remove('hidden');
        }
    });
    window.location.href = `update_status.php?id=${docId}&status=${status}&reason=${encodeURIComponent(reason)}&alumni_id=${alumniId}&doc_type=${encodeURIComponent(docType)}`;
}

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.view-docs-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            const userId = e.target.dataset.userId;
            showDocs(userId);
        });
    });
    setupBatchFilter();
    setupEmploymentFilter();
    showBatchOverview();
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showToast(urlParams.get('success'));
    } else if (urlParams.has('error')) {
        showToast(urlParams.get('error'), 'error');
    }
    document.getElementById("alumniSearchInput")?.addEventListener("input", () => {
        if (!document.getElementById('alumniBatchDetailContainer').classList.contains('hidden')) {
            renderAlumniDetailTable();
        } else {
            renderBatchOverview();
        }
    });
    document.getElementById('backToOverviewBtn')?.addEventListener('click', showBatchOverview);
});
</script>
<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>