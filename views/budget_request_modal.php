
<link
  href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css"
  rel="stylesheet"
/>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>


<div
  class="modal fade"
  id="budgetModal"
  tabindex="-1"
  aria-labelledby="budgetModalLabel"
  aria-hidden="true"
>
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background-color:#f5f5f5; border-radius:8px;">
      <!-- Header -->
      <div
        class="modal-header"
        style="
          background-color: #006837;
          color: #fff;
          border-top-left-radius: 8px;
          border-top-right-radius: 8px;
        "
      >
        <h5 class="modal-title" id="budgetModalLabel">
          Budget Request: <span id="br-id">—</span>
        </h5>
        <button
          type="button"
          class="btn-close btn-close-white"
          data-bs-dismiss="modal"
          aria-label="Close"
        ></button>
      </div>

      <!-- Body -->
      <div class="modal-body">
        <!-- Basic Info -->
        <h6 class="text-success mb-3">Basic Information</h6>
        <div class="row gx-3 mb-3">
          <div class="col-md-4">
            <div class="p-3 border rounded">
              <strong>Submitted By:</strong><br />
              <span id="submitted-name">—</span><br />
              <small class="text-muted" id="submitted-email">—</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 border rounded">
              <strong>Department:</strong><br />
              <span id="dept-name">—</span> (<span id="dept-code">—</span>)
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 border rounded">
              <strong>College:</strong><br />
              <span id="college">—</span>
            </div>
          </div>
        </div>
        <div class="row gx-3 mb-3">
          <div class="col-md-4">
            <div class="p-3 border rounded">
              <strong>Academic Year:</strong><br />
              <span id="acad-year">—</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 border rounded">
              <strong>Submission Date:</strong><br />
              <span id="sub-date">—</span>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <div class="p-3 border rounded">
            <strong>Request Title:</strong><br />
            <span id="req-title">—</span>
          </div>
        </div>

        <div class="mb-3">
          <div class="p-3 border rounded">
            <strong>Justification:</strong><br />
            <span id="justification">—</span>
          </div>
        </div>

        <div class="mb-3">
          <div class="p-3 border rounded">
            <strong>Attached budget deck:</strong><br />
            <a href="#" id="deck-link">—</a>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center p-3 border rounded mb-4">
          <div><strong>TOTAL PROPOSED BUDGET:</strong></div>
          <div class="h5"><strong id="total-budget">—</strong></div>
        </div>

        <!-- Line Items -->
        <h6 class="text-success mb-3">Line Item Details</h6>
        <div id="line-items-container"></div>
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button
          type="button"
          class="btn btn-secondary"
          data-bs-dismiss="modal"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</div>


<style>
  #line-items-container .line-item {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
  }
  #line-items-container .line-item h6 {
    color: #006837;
  }
  #line-items-container .breakdown {
    background-color: #e9f7ef;
    border-radius: 4px;
    padding: 0.75rem;
    font-size: 0.9rem;
    line-height: 1.4;
  }
</style>


<script>
 
  const budgetModal = new bootstrap.Modal(
    document.getElementById("budgetModal")
  );

  
  function showBudgetRequest(data) {
    document.getElementById("br-id").textContent = data.id;
    document.getElementById("submitted-name").textContent =
      data.submittedBy.name;
    document.getElementById("submitted-email").textContent =
      data.submittedBy.email;
    document.getElementById("dept-name").textContent = data.department.name;
    document.getElementById("dept-code").textContent = data.department.code;
    document.getElementById("college").textContent = data.college;
    document.getElementById("acad-year").textContent = data.academicYear;
    document.getElementById("sub-date").textContent = data.submissionDate;
    document.getElementById("req-title").textContent = data.requestTitle;
    document.getElementById("justification").textContent = data.justification;
    const deckLink = document.getElementById("deck-link");
    deckLink.textContent = data.deck.filename;
    deckLink.href = data.deck.url;
    document.getElementById("total-budget").textContent = data.totalBudget;

   
    const container = document.getElementById("line-items-container");
    container.innerHTML = "";
    data.lineItems.forEach((item) => {
      const div = document.createElement("div");
      div.className = "line-item";
      div.innerHTML = `
        <h6>GL Account: ${item.glAccount} – ${item.glDesc}</h6>
        <p><strong>BPR Line Item:</strong> ${item.bprLine}</p>
        <p><strong>Description:</strong> ${item.description}</p>
        <p><strong>Total Amount:</strong> ${item.totalAmount}</p>
        <p><strong>Distribution Chosen:</strong> ${item.distribution}</p>
        <div class="breakdown">
          ${item.breakdown.join(" | ")}
        </div>
      `;
      container.appendChild(div);
    });

    budgetModal.show();
  }

  
  document
    .querySelectorAll(".budget-request-card")
    .forEach((card) =>
      card.addEventListener("click", () =>
        showBudgetRequest(JSON.parse(card.dataset.request))
      )
    );
</script>
