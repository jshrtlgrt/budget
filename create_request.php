<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Create Budget Request (FHIT)</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; font-family: 'Montserrat', sans-serif; }
    body { margin: 0; background: #f4f4f4; }
    .container { padding: 30px; background-color: #fff; border-radius: 8px; margin: 20px auto; max-width: 1300px; }
    h1 { text-align: center; color: #004d26; margin-bottom: 30px; }
    .form-section, .budget-section { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
    .form-group { flex: 1 1 200px; min-width: 220px; }
    .form-group label { font-weight: 600; }
    input, select, textarea {
      width: 100%; padding: 10px; border-radius: 8px;
      border: 1px solid #ccc; margin-top: 8px;
    }
    .left-panel {
      width: 100%; padding: 20px; border: 2px solid #ccc;
      border-radius: 10px; margin-bottom: 30px;
    }
    .entry-row {
      display: flex; align-items: center; gap: 10px; margin-bottom: 10px;
    }
    .entry-row select, .entry-row input[type="number"], .entry-row input[type="text"] {
      padding: 8px; border-radius: 6px;
    }
    .entry-row input[readonly] {
      background-color: #f0f0f0; border: 1px solid #aaa;
    }
    .budget-summary {
      background: #f0f4f7;
      border: 4px solid #006633;
      padding: 20px 30px;
      border-radius: 20px;
    }
    .budget-summary h3 { font-size: 20px; margin-bottom: 10px; }
    .total {
      font-size: 22px;
      text-align: right;
      font-weight: bold;
      color: #004d26;
    }
    .btn {
      padding: 12px 20px;
      font-weight: bold;
      border-radius: 25px;
      cursor: pointer;
    }
    .submit { background: #006633; color: #fff; border: none; }
    .back-btn { text-decoration: none; color: #006633; font-weight: bold; }
    
    /* Distribution Modal Styles */
    .distribution-modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
    }
    
    .distribution-modal-content {
      background-color: #fff;
      margin: 10% auto;
      padding: 30px;
      border-radius: 10px;
      width: 80%;
      max-width: 600px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    
    .distribution-close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }
    
    .distribution-close:hover {
      color: #000;
    }
    
    .clickable-amount {
      color: #006633;
      text-decoration: underline;
      cursor: pointer;
    }
    
    .clickable-amount:hover {
      color: #004d26;
      font-weight: bold;
    }
    
    .distribution-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    
    .distribution-table th,
    .distribution-table td {
      padding: 10px;
      border: 1px solid #ddd;
      text-align: left;
    }
    
    .distribution-table th {
      background-color: #f8f9fa;
      font-weight: bold;
    }
  </style>
</head>
<body>
<form method="POST" action="submit_request.php" onsubmit="prepareEntryData()">
  <div class="container">
    <div><a href="requester.php" class="back-btn">← Back to Dashboard</a></div>
    <h1>Create Budget Request (FHIT)</h1>

    <div class="form-section">
      <div class="form-group">
        <label for="campus">Campus Code</label>
        <select name="campus" required>
          <option value="11">11 - Manila</option>
          <option value="12">12 - Makati</option>
          <option value="13">13 - McKinley</option>
          <option value="21">21 - Laguna</option>
          <option value="31">31 - BGC</option>
        </select>
      </div>
      <div class="form-group">
        <label for="department">Department Code</label>
        <input type="text" name="department" value="999" required />
      </div>
      <div class="form-group">
        <label for="fund_account">Fund Account Code</label>
        <input type="text" name="fund_account" value="62000701" required />
      </div>
      <div class="form-group">
        <label for="fund_name">Fund Name</label>
        <input type="text" name="fund_name" placeholder="Enter fund name..." required />
      </div>
      <div class="form-group">
        <label for="duration">Duration</label>
        <select name="duration" id="duration">
          <option value="Annually">Annually</option>
          <option value="Quarterly">Quarterly</option>
          <option value="Monthly">Monthly</option>
        </select>
      </div>
    </div>

    <div class="budget-section">
      <div class="form-group" style="flex: 1 1 100%;">
        <label for="budget_title">Budget Request Title</label>
        <input type="text" name="budget_title" required />
      </div>
      <div class="form-group" style="flex: 1 1 100%;">
        <label for="description">Description</label>
        <textarea name="description" rows="3" required></textarea>
      </div>
    </div>
        <!-- Entry Panel -->
    <div class="left-panel">
      <h3>Proposed Itemized Budget</h3>
      <div id="entry-section"></div>
      <button type="button" class="btn" onclick="addEntry()">+ Add Entry</button>
    </div>

    <!-- Summary -->
    <div class="budget-summary">
      <h3>Budget Summary</h3>
      <div id="summary-list"></div>
      <div class="total" id="total">Total: PHP 0.00</div>
    </div>

    <input type="hidden" name="budget_entries" id="budget_entries_json">
    <br>
    <button type="submit" class="btn submit">Submit Request</button>
  </div>
</form>

<!-- Distribution Modal -->
<div id="distributionModal" class="distribution-modal">
  <div class="distribution-modal-content">
    <span class="distribution-close" onclick="closeDistributionModal()">&times;</span>
    <h2>Budget Distribution Details</h2>
    <div id="distributionDetails">
      <!-- Distribution details will be populated here -->
    </div>
  </div>
</div>

<script>
const glOptions = {
  "FHIT - SALARIES - 1": "210304001", "FHIT - SALARIES - 2": "210304002", "FHIT - SALARIES - 3": "210304003",
  "FHIT - SALARIES - 4": "210304004", "FHIT - SALARIES - 5": "210304005", "FHIT - SALARIES - 6": "210304006",
  "FHIT - SALARIES - 7": "210304007", "FHIT - SALARIES - 8": "210304008", "FHIT - SALARIES - 9": "210304009",
  "FHIT - SALARIES - 10": "210304010",
  "FHIT - HONORARIA - 1": "210305001", "FHIT - HONORARIA - 2": "210305002", "FHIT - HONORARIA - 3": "210305003",
  "FHIT - HONORARIA - 4": "210305004", "FHIT - HONORARIA - 5": "210305005", "FHIT - HONORARIA - 6": "210305006",
  "FHIT - HONORARIA - 7": "210305007", "FHIT - HONORARIA - 8": "210305008", "FHIT - HONORARIA - 9": "210305009",
  "FHIT - HONORARIA - 10": "210305010",
  "FHIT - PROFESSIONAL FEE - 1": "210306001", "FHIT - PROFESSIONAL FEE - 2": "210306002", "FHIT - PROFESSIONAL FEE - 3": "210306003",
  "FHIT - PROFESSIONAL FEE - 4": "210306004", "FHIT - PROFESSIONAL FEE - 5": "210306005", "FHIT - PROFESSIONAL FEE - 6": "210306006",
  "FHIT - PROFESSIONAL FEE - 7": "210306007", "FHIT - PROFESSIONAL FEE - 8": "210306008", "FHIT - PROFESSIONAL FEE - 9": "210306009",
  "FHIT - PROFESSIONAL FEE - 10": "210306010",
  "FHIT - TRANSPORTATION AND DELIVERY EXPENSES": "210303007",
  "FHIT - TRAVEL (LOCAL)": "210303028",
  "FHIT - TRAVEL (FOREIGN)": "210303029",
  "FHIT - ACCOMMODATION AND VENUE": "210303025",
  "FHIT - TRAVEL ALLOWANCE / PER DIEM": "210303003",
  "FHIT - FOOD AND MEALS": "210303026",
  "FHIT - REPRESENTATION EXPENSES": "210303018",
  "FHIT - REPAIRS AND MAINTENANCE OF FACILITIES": "210303005",
  "FHIT - REPAIRS AND MAINTENANCE OF VEHICLES": "210303006",
  "FHIT - SUPPLIES AND MATERIALS EXPENSES": "210303008",
  "FHIT - ADVERTISING EXPENSES": "210303015",
  "FHIT - PRINTING AND BINDING EXPENSES": "210303016",
  "FHIT - GENERAL SERVICES": "210303014",
  "FHIT - COMMUNICATION EXPENSES": "210303004",
  "FHIT - UTILITY EXPENSES": "210303009",
  "FHIT - SCHOLARSHIP EXPENSES": "210303011",
  "FHIT - TRAINING, WORKSHOP, CONFERENCE": "210303010",
  "FHIT - MEMBERSHIP FEE": "210303027",
  "FHIT - INDIRECT COST - RESEARCH FEE": "210303040",
  "FHIT - WITHDRAWAL OF FUND": "210303043",
  "FHIT - AWARDS/REWARDS, PRICES AND INDEMNITIES": "210303012",
  "FHIT - SURVEY, RESEARCH, EXPLORATION AND DEVELOPMENT EXPENSES": "210303013",
  "FHIT - RENT EXPENSES": "210303017",
  "FHIT - SUBSCRIPTION EXPENSES": "210303019",
  "FHIT - DONATIONS": "210303020",
  "FHIT - TAXES, INSURANCE PREMIUMS AND OTHER FEES": "210303022",
  "FHIT - OTHER MAINTENANCE AND OPERATING EXPENSES": "210303023",
  "Others": ""
};

const entries = [];
const usedSequential = new Set();
function addEntry() {
  const section = document.getElementById("entry-section");
  const row = document.createElement("div");
  row.className = "entry-row";

  // Dropdown
  const select = document.createElement("select");
  select.required = true;

  // Placeholder FIRST (forces user action; can't submit with this)
  const ph = document.createElement("option");
  ph.value = "";
  ph.textContent = "— Choose FHIT Item —";
  ph.disabled = true;
  ph.selected = true;
  select.appendChild(ph);

  // Build available list respecting sequential rules
  const available = Object.keys(glOptions).filter(label => {
    if (label === "Others") return true;
    const match = label.match(/(FHIT - [A-Z &/]+) - (\d+)/);
    if (match) {
      const base = match[1];
      const num = parseInt(match[2], 10);
      const prev = `${base} - ${num - 1}`;
      if (num > 1 && !usedSequential.has(prev)) return false; // can't see -2 until -1 used
      if (usedSequential.has(label)) return false;            // already used, hide
    } else {
      // non-numbered: hide if used
      if (usedSequential.has(label)) return false;
    }
    return true;
  });

  // Append actual selectable options
  available.forEach(label => {
    const opt = document.createElement("option");
    opt.value = label;
    opt.textContent = label;
    select.appendChild(opt);
  });

  // GL Code display
const glCodeInput = document.createElement("input");
glCodeInput.type = "text";
glCodeInput.readOnly = true;
glCodeInput.placeholder = "GL Code";

// 'Others' description
const otherDesc = document.createElement("input");
otherDesc.type = "text";
otherDesc.placeholder = "Describe (for Others)";
otherDesc.style.display = "none";

// Remarks field
const remarksInput = document.createElement("input");
remarksInput.type = "text";
remarksInput.placeholder = "Remarks (optional)";

// Amount
const amountInput = document.createElement("input");
amountInput.type = "text"; // allow ₱ formatting if used
amountInput.placeholder = "₱ 0.00";


  // Remove button
  const removeBtn = document.createElement("button");
  removeBtn.type = "button";
  removeBtn.textContent = "🗑";
  removeBtn.onclick = () => {
    const currentVal = select.value;
    if (currentVal) {
      usedSequential.delete(currentVal); // free up for reuse
    }
    row.remove();
    updateSummary();
  };

  // Track last applied label so we can release it if user changes selection
  let lastSelected = "";

  select.onchange = () => {
  const label = select.value;

  // Remove all except the first dropdown
  while (row.children.length > 1) {
    row.removeChild(row.lastChild);
  }

  if (!label) {
    glCodeInput.value = "";
    lastSelected = "";
    updateSummary();
    return;
  }

  // Add the proper inputs based on selection
  if (label === "Others") {
    otherDesc.style.display = "inline-block";
    glCodeInput.style.display = "none";

    row.appendChild(otherDesc);
    row.appendChild(remarksInput);
    row.appendChild(amountInput);
  } else {
    otherDesc.style.display = "none";
    glCodeInput.style.display = "inline-block";
    glCodeInput.value = glOptions[label] || "";

    row.appendChild(glCodeInput);
    row.appendChild(remarksInput);
    row.appendChild(amountInput);
  }

  row.appendChild(removeBtn); // Always last
  usedSequential.add(label);
  lastSelected = label;
  updateSummary();
};

  amountInput.addEventListener("input", () => {
  updateSummary(); // live update on every keystroke
});

amountInput.addEventListener("blur", () => {
  let val = amountInput.value.replace(/[^\d.]/g, '');
  if (val) {
    amountInput.value = '₱ ' + parseFloat(val).toFixed(2);
  } else {
    amountInput.value = '';
  }
  updateSummary(); // ensure formatting still updates total correctly
});



  otherDesc.oninput = updateSummary;

  row.appendChild(select);

// Default layout (can be overridden in onchange)
if (select.value === "Others") {
  row.appendChild(otherDesc);
  row.appendChild(remarksInput);
  row.appendChild(amountInput);
} else {
  row.appendChild(glCodeInput);
  row.appendChild(remarksInput);
  row.appendChild(amountInput);
}
row.appendChild(removeBtn);

  section.appendChild(row);
}


function updateSummary() {
  const rows = document.querySelectorAll(".entry-row");
  const summary = document.getElementById("summary-list");
  const totalElem = document.getElementById("total");
  let data = [];
  let total = 0;

  rows.forEach(row => {
    const select = row.querySelector("select");
    const labelSelected = select.value;
    if (!labelSelected) return; // skip placeholder rows

    const inputs = row.querySelectorAll("input");
    let amountInput, glInput, otherInput, remarksInput;

    inputs.forEach(input => {
    if (input.placeholder.includes('₱')) amountInput = input;
    else if (input.placeholder.includes('GL')) glInput = input;
    else if (input.placeholder.includes('Describe')) otherInput = input;
    else if (input.placeholder.includes('Remarks')) remarksInput = input;
    });

    const amountRaw = amountInput?.value.replace(/[^\d.]/g, '') || '0';
    const amount = parseFloat(amountRaw) || 0;
    const otherDesc = otherInput?.value.trim() || '';
    const remarks = remarksInput?.value.trim() || '';

    const displayLabel = (labelSelected === "Others")
    ? `Others - ${otherDesc}`
    : labelSelected;


    const glCode = glOptions[labelSelected] || "";

    if (amount > 0) {
      data.push({ label: displayLabel, gl_code: glCode, remarks: remarks, amount });
      total += amount;
    }
  });

  document.getElementById("budget_entries_json").value = JSON.stringify(data);

  summary.innerHTML = "";
  data.forEach(item => {
    const duration = document.getElementById('duration').value;
    summary.innerHTML += `<div>${item.label}: <span class="clickable-amount" onclick="showDistribution('${item.gl_code}', '${item.label.replace(/'/g, "\\'")}', ${item.amount}, '${duration}')">₱ ${item.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>`;
  });
  totalElem.textContent = "Total: ₱ " + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

}
function prepareEntryData() {
  updateSummary(); // ensure latest data serialized before submit
}

// Budget Distribution Functions
function getAcademicYearMonths() {
  // Academic year runs from September 2025 to August 2026
  const months = [];
  
  // September to December 2025
  for (let month = 9; month <= 12; month++) {
    months.push({
      month: month,
      year: 2025,
      name: new Date(2025, month - 1).toLocaleString('default', { month: 'long' }) + ' 2025'
    });
  }
  
  // January to August 2026
  for (let month = 1; month <= 8; month++) {
    months.push({
      month: month,
      year: 2026,
      name: new Date(2026, month - 1).toLocaleString('default', { month: 'long' }) + ' 2026'
    });
  }
  
  return months;
}

function calculateDistribution(totalAmount, duration) {
  const months = getAcademicYearMonths();
  const distribution = [];
  
  switch(duration) {
    case 'Monthly':
      const monthlyAmount = totalAmount / months.length;
      months.forEach(month => {
        distribution.push({
          period: month.name,
          amount: monthlyAmount
        });
      });
      break;
      
    case 'Quarterly':
      const quarterlyAmount = totalAmount / 4;
      const quarters = [
        { name: 'Q1 (Sep-Nov 2025)', months: months.slice(0, 3) },
        { name: 'Q2 (Dec 2025 - Feb 2026)', months: months.slice(3, 6) },
        { name: 'Q3 (Mar-May 2026)', months: months.slice(6, 9) },
        { name: 'Q4 (Jun-Aug 2026)', months: months.slice(9, 12) }
      ];
      
      quarters.forEach(quarter => {
        distribution.push({
          period: quarter.name,
          amount: quarterlyAmount
        });
      });
      break;
      
    case 'Annually':
    default:
      distribution.push({
        period: 'Annual (Sep 2025 - Aug 2026)',
        amount: totalAmount
      });
      break;
  }
  
  return distribution;
}

function showDistribution(glCode, description, amount, duration) {
  const distribution = calculateDistribution(amount, duration);
  
  let detailsHTML = `
    <div style="margin-bottom: 20px;">
      <p><strong>Line Item:</strong> ${glCode}</p>
      <p><strong>Description:</strong> ${description}</p>
      <p><strong>Total Amount:</strong> PHP ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
      <p><strong>Distribution Chosen:</strong> ${duration}</p>
    </div>
    
    <table class="distribution-table">
      <thead>
        <tr>
          <th>Period</th>
          <th>Amount</th>
        </tr>
      </thead>
      <tbody>
  `;
  
  distribution.forEach(item => {
    detailsHTML += `
      <tr>
        <td>${item.period}</td>
        <td>PHP ${item.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
      </tr>
    `;
  });
  
  detailsHTML += `
      </tbody>
    </table>
  `;
  
  document.getElementById('distributionDetails').innerHTML = detailsHTML;
  document.getElementById('distributionModal').style.display = 'block';
}

function closeDistributionModal() {
  document.getElementById('distributionModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
  const modal = document.getElementById('distributionModal');
  if (event.target === modal) {
    modal.style.display = 'none';
  }
}

</script>
</body>
</html>
