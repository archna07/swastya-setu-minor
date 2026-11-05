/* ==========================================================================
   Swastya Setu - Platform JavaScript Logic & SPA Router
   ========================================================================== */

// Global Application State Data
const state = {
  activeView: 'view-landing',
  cart: [
    { id: 1, name: 'Amoxicillin 500mg', price: 120, qty: 1, rx: true },
    { id: 2, name: 'Paracetamol 650mg', price: 45, qty: 1, rx: false }
  ],
  selectedSymptoms: new Set(),
  uploadedRxFile: null
};

// Centralized Symptom Catalog
const symptomsList = [
  { id: 'cough', label: 'Cough', category: 'Respiratory', is_emergency: false },
  { id: 'shortness_of_breath', label: 'Shortness of Breath', category: 'Respiratory', is_emergency: true },
  { id: 'chest_pain', label: 'Chest Pain', category: 'Respiratory', is_emergency: true },
  { id: 'sore_throat', label: 'Sore Throat', category: 'Respiratory', is_emergency: false },
  { id: 'runny_nose', label: 'Runny Nose', category: 'Respiratory', is_emergency: false },
  { id: 'sneezing', label: 'Sneezing', category: 'Respiratory', is_emergency: false },
  { id: 'wheezing', label: 'Wheezing / Whistling Breath', category: 'Respiratory', is_emergency: false },
  { id: 'loss_of_smell', label: 'Loss of Smell / Taste', category: 'Respiratory', is_emergency: false },
  { id: 'fever', label: 'Mild / Moderate Fever', category: 'Systemic', is_emergency: false },
  { id: 'high_fever', label: 'High Fever (> 102°F)', category: 'Systemic', is_emergency: false },
  { id: 'chills', label: 'Chills & Shivering', category: 'Systemic', is_emergency: false },
  { id: 'sweating', label: 'Excessive Sweating', category: 'Systemic', is_emergency: false },
  { id: 'fatigue', label: 'Fatigue & Weakness', category: 'Systemic', is_emergency: false },
  { id: 'body_ache', label: 'Generalized Body Ache', category: 'Systemic', is_emergency: false },
  { id: 'nausea', label: 'Nausea', category: 'Digestive', is_emergency: false },
  { id: 'vomiting', label: 'Vomiting', category: 'Digestive', is_emergency: false },
  { id: 'abdominal_pain', label: 'Abdominal / Stomach Pain', category: 'Digestive', is_emergency: false },
  { id: 'diarrhea', label: 'Diarrhea / Loose Stools', category: 'Digestive', is_emergency: false },
  { id: 'acidity', label: 'Acidity & Heartburn', category: 'Digestive', is_emergency: false },
  { id: 'yellow_skin', label: 'Yellow Skin / Eyes (Jaundice)', category: 'Digestive', is_emergency: false },
  { id: 'dark_urine', label: 'Dark / Amber-Colored Urine', category: 'Digestive', is_emergency: false },
  { id: 'loss_of_appetite', label: 'Loss of Appetite', category: 'Digestive', is_emergency: false },
  { id: 'headache', label: 'Headache', category: 'Neurological', is_emergency: false },
  { id: 'dizziness', label: 'Dizziness / Lightheadedness', category: 'Neurological', is_emergency: false },
  { id: 'blurred_vision', label: 'Blurred / Dimmed Vision', category: 'Neurological', is_emergency: false },
  { id: 'stiff_neck', label: 'Stiff Neck / Neck Rigidity', category: 'Neurological', is_emergency: true },
  { id: 'confusion', label: 'Confusion / Disorientation', category: 'Neurological', is_emergency: true },
  { id: 'excessive_thirst', label: 'Excessive Thirst', category: 'Metabolic & Urinary', is_emergency: false },
  { id: 'frequent_urination', label: 'Frequent Urination', category: 'Metabolic & Urinary', is_emergency: false },
  { id: 'burning_urination', label: 'Burning Urination', category: 'Metabolic & Urinary', is_emergency: false },
  { id: 'high_blood_pressure', label: 'High Blood Pressure', category: 'Metabolic & Urinary', is_emergency: false },
  { id: 'joint_pain', label: 'Joint Pain & Stiffness', category: 'Musculoskeletal', is_emergency: false },
  { id: 'swollen_joints', label: 'Swollen Joints', category: 'Musculoskeletal', is_emergency: false },
  { id: 'muscle_weakness', label: 'Muscle Weakness', category: 'Musculoskeletal', is_emergency: false },
  { id: 'skin_rash', label: 'Skin Rash', category: 'Dermatology', is_emergency: false },
  { id: 'itching', label: 'Severe Skin Itching', category: 'Dermatology', is_emergency: false }
];

const hospitalsData = [
  {
    name: 'Apollo Multi-Specialty Hospital',
    city: 'New Delhi',
    spec: 'Cardiology',
    type: 'Private',
    rating: '4.9 ★',
    dist: '2.4 km',
    beds: 14,
    doctors: 38,
    emergency: '24x7 Active',
    badgeClass: 'badge-private'
  },
  {
    name: 'AIIMS Central Trauma Center',
    city: 'New Delhi',
    spec: 'Neurology',
    type: 'Govt',
    rating: '4.8 ★',
    dist: '4.1 km',
    beds: 2,
    doctors: 85,
    emergency: '24x7 ICU Open',
    badgeClass: 'badge-govt'
  },
  {
    name: 'Fortis Healthcare & Research',
    city: 'Gurugram',
    spec: 'Orthopedics',
    type: 'Private',
    rating: '4.7 ★',
    dist: '6.8 km',
    beds: 22,
    doctors: 45,
    emergency: '24x7 Active',
    badgeClass: 'badge-private'
  }
];

const doctorsData = [
  {
    name: 'Dr. Priya Verma',
    qual: 'MBBS, MD (Cardiology)',
    exp: '14 Years Exp',
    spec: 'Cardiology Specialist',
    fee: '₹800',
    slots: ['10:30 AM', '02:00 PM', '05:30 PM']
  },
  {
    name: 'Dr. Rajesh Sharma',
    qual: 'MBBS, MS (Neurology)',
    exp: '18 Years Exp',
    spec: 'Senior Neurologist',
    fee: '₹1,200',
    slots: ['11:00 AM', '03:15 PM']
  },
  {
    name: 'Dr. Sunita Rao',
    qual: 'MBBS, DCH (Pediatrics)',
    exp: '10 Years Exp',
    spec: 'Child Care & Pediatrics',
    fee: '₹600',
    slots: ['09:30 AM', '01:00 PM', '04:00 PM']
  }
];

const pharmaciesData = [
  {
    name: 'Apollo Pharmacy 24x7',
    rating: '4.8 ★',
    dist: '0.8 km',
    time: '20 Mins Delivery',
    type: '24x7 Open',
    disc: '20% Off on Generic'
  },
  {
    name: 'Tata 1mg Express Hub',
    rating: '4.9 ★',
    dist: '1.5 km',
    time: '30 Mins Delivery',
    type: 'Express Delivery',
    disc: '15% Off'
  },
  {
    name: 'MedPlus Chemist',
    rating: '4.6 ★',
    dist: '2.1 km',
    time: '45 Mins Delivery',
    type: 'Open Now',
    disc: 'Flat 10% Discount'
  }
];

// Initialize DOM on Load
document.addEventListener('DOMContentLoaded', () => {
  renderSymptomChips();
  renderHospitals(hospitalsData);
  renderDoctors(doctorsData);
  renderPharmacies(pharmaciesData);
  updateCartUI();
});

// View Switcher Router
function switchView(viewId) {
  state.activeView = viewId;

  // Toggle View Display
  document.querySelectorAll('.view-section').forEach(view => {
    view.classList.remove('active');
  });
  const targetView = document.getElementById(viewId);
  if (targetView) targetView.classList.add('active');

  // Toggle Sidebar Active Styling
  document.querySelectorAll('.sidebar-item').forEach(item => {
    item.classList.remove('active');
  });
  const activeSidebarItem = document.querySelector(`.sidebar-item[onclick*="${viewId}"]`);
  if (activeSidebarItem) activeSidebarItem.classList.add('active');

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Symptom Chips Logic
function renderSymptomChips() {
  const box = document.getElementById('symptom-chip-box');
  if (!box) return;
  box.innerHTML = '';
  symptomsList.forEach(symptom => {
    const isSelected = state.selectedSymptoms.has(symptom.id);
    const chip = document.createElement('div');
    const emergencyCls = symptom.is_emergency ? 'emergency-chip' : '';
    chip.className = `chip ${isSelected ? 'selected' : ''} ${emergencyCls}`;
    chip.innerHTML = `${symptom.is_emergency ? '<i class="fa-solid fa-triangle-exclamation text-danger"></i>' : '<i class="fa-solid fa-plus"></i>'} ${symptom.label}`;
    chip.onclick = () => toggleSymptom(symptom.id);
    box.appendChild(chip);
  });
}

function toggleSymptom(symptomId) {
  if (state.selectedSymptoms.has(symptomId)) {
    state.selectedSymptoms.delete(symptomId);
  } else {
    state.selectedSymptoms.add(symptomId);
  }
  renderSymptomChips();
  document.getElementById('selected-symptom-count').innerText = `${state.selectedSymptoms.size} symptoms selected`;
}

function filterSymptomChips() {
  const query = document.getElementById('symptom-search').value.toLowerCase();
  const chips = document.querySelectorAll('#symptom-chip-box .chip');
  chips.forEach(chip => {
    const text = chip.innerText.toLowerCase();
    chip.style.display = text.includes(query) ? 'inline-flex' : 'none';
  });
}

async function runDiseasePrediction() {
  if (state.selectedSymptoms.size === 0) {
    showToast('Please select at least 1 symptom to run prediction.', 'warning');
    return;
  }
  
  showToast('AI Clinical Decision Engine analyzing symptom presentation...', 'info');
  const btn = document.querySelector('button[onclick="runDiseasePrediction()"]');
  if (btn) btn.disabled = true;

  try {
    const payload = { symptoms: Array.from(state.selectedSymptoms) };
    const response = await fetch('http://127.0.0.1:5000/predict', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      throw new Error(`AI Service responded with HTTP ${response.status}`);
    }

    const data = await response.json();
    if (data.status === 'success' || data.primary_condition) {
      const titleElem = document.getElementById('pred-disease-title');
      if (titleElem) titleElem.innerText = data.primary_condition;

      const descElem = document.getElementById('pred-disease-desc');
      if (descElem) descElem.innerText = data.description || '';

      const confElem = document.getElementById('pred-confidence-text');
      if (confElem) confElem.innerText = `${data.confidence}%`;

      const barElem = document.getElementById('pred-confidence-bar');
      if (barElem) barElem.style.width = `${Math.min(100, Math.max(5, data.confidence))}%`;

      const specElem = document.getElementById('pred-specialist');
      if (specElem) specElem.innerText = data.specialist || 'General Physician';

      const medElem = document.getElementById('pred-medicines');
      if (medElem) {
        // Clinical Safety: Display non-prescription self-care rather than prescription drugs!
        medElem.innerText = 'Consult doctor for prescription. Use ORS, fluids & rest as needed.';
      }

      const precElem = document.getElementById('pred-precautions');
      if (precElem && data.precautions) {
        precElem.innerText = data.precautions.slice(0, 3).join('; ');
      }

      // Emergency guidance banner
      let emBanner = document.getElementById('pred-emergency-alert');
      if (!emBanner) {
        emBanner = document.createElement('div');
        emBanner.id = 'pred-emergency-alert';
        emBanner.style.cssText = 'background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: none;';
        const cardBody = document.getElementById('prediction-result-card');
        if (cardBody) cardBody.insertBefore(emBanner, cardBody.firstChild);
      }

      if (data.emergency_alert) {
        emBanner.style.display = 'block';
        emBanner.innerHTML = `<strong><i class="fa-solid fa-triangle-exclamation"></i> EMERGENCY RED-FLAG WARNING:</strong> ${data.emergency_guidance} <div style="margin-top: 0.5rem;"><button class="btn btn-danger btn-sm" onclick="openSOSModal()"><i class="fa-solid fa-phone-volume"></i> Trigger 108 SOS Now</button></div>`;
      } else {
        emBanner.style.display = 'none';
      }

      const card = document.getElementById('prediction-result-card');
      card.style.display = 'block';
      card.scrollIntoView({ behavior: 'smooth' });
      showToast(`Analysis complete: Pattern matched to ${data.primary_condition}`, 'success');
    } else {
      showToast(data.message || 'Could not analyze symptoms.', 'warning');
    }
  } catch (err) {
    console.error('AI Predict fetch error:', err);
    showToast('AI Service offline. Please sign in to the patient portal or consult a doctor.', 'danger');
  } finally {
    if (btn) btn.disabled = false;
  }
}

// Render Hospital Cards
function renderHospitals(list) {
  const grid = document.getElementById('hospitals-card-grid');
  if (!grid) return;
  grid.innerHTML = '';
  list.forEach(hosp => {
    grid.innerHTML += `
      <div class="card hospital-card">
        <div class="card-img-placeholder">
          <i class="fa-solid fa-hospital"></i>
          <span class="card-badge ${hosp.badgeClass}">${hosp.type}</span>
        </div>
        <div>
          <h3>${hosp.name}</h3>
          <p style="color: var(--text-muted); font-size: 0.85rem;"><i class="fa-solid fa-location-dot"></i> ${hosp.city} • ${hosp.dist}</p>
          <div style="margin-top: 8px; display: flex; gap: 12px; font-size: 0.9rem;">
            <span><strong>ICU Beds:</strong> ${hosp.beds}</span>
            <span><strong>Doctors:</strong> ${hosp.doctors}</span>
          </div>
        </div>
        <button class="btn btn-primary" onclick="switchView('view-doctor-appointment')">
          <i class="fa-solid fa-calendar-check"></i> Book Appointment
        </button>
      </div>
    `;
  });
}

function filterHospitals() {
  const query = document.getElementById('hosp-search-input').value.toLowerCase();
  const spec = document.getElementById('hosp-spec-select').value;
  const type = document.getElementById('hosp-type-select').value;

  const filtered = hospitalsData.filter(h => {
    const matchName = h.name.toLowerCase().includes(query) || h.city.toLowerCase().includes(query);
    const matchSpec = spec === 'all' || h.spec === spec;
    const matchType = type === 'all' || h.type === type;
    return matchName && matchSpec && matchType;
  });
  renderHospitals(filtered);
}

// Render Doctor Cards
function renderDoctors(list) {
  const grid = document.getElementById('doctors-card-grid');
  if (!grid) return;
  grid.innerHTML = '';
  list.forEach(doc => {
    grid.innerHTML += `
      <div class="card doctor-card">
        <div style="display: flex; gap: 12px; align-items: center;">
          <i class="fa-solid fa-user-doctor" style="font-size: 3rem; color: var(--primary);"></i>
          <div>
            <h3>${doc.name}</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted);">${doc.qual}</p>
            <span class="badge-card badge-open" style="margin-top: 4px;">${doc.exp}</span>
          </div>
        </div>
        <div>
          <p><strong>Fee:</strong> ${doc.fee} per consult</p>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">Available Slots: ${doc.slots.join(', ')}</p>
        </div>
        <div style="display: flex; gap: 8px;">
          <button class="btn btn-primary btn-sm" onclick="showToast('Appointment Booked with ${doc.name}!', 'success')">
            <i class="fa-solid fa-calendar-plus"></i> Book Visit
          </button>
          <button class="btn btn-secondary btn-sm" onclick="showToast('Initiating Teleconsult Video Room...', 'info')">
            <i class="fa-solid fa-video"></i> Video Call
          </button>
        </div>
      </div>
    `;
  });
}

// Render Pharmacies
function renderPharmacies(list) {
  const grid = document.getElementById('pharmacy-card-grid');
  if (!grid) return;
  grid.innerHTML = '';
  list.forEach(p => {
    grid.innerHTML += `
      <div class="card pharmacy-card">
        <div>
          <h3><i class="fa-solid fa-prescription-bottle-medical" style="color: var(--primary);"></i> ${p.name}</h3>
          <p style="font-size: 0.85rem; color: var(--text-muted);">${p.rating} • ${p.dist} away</p>
          <span class="badge-card badge-open" style="margin-top: 8px;">${p.type}</span>
          <p style="color: var(--success); font-weight: 600; font-size: 0.85rem; margin-top: 6px;">${p.disc}</p>
        </div>
        <div style="display: flex; gap: 8px; flex-direction: column;">
          <button class="btn btn-primary" onclick="switchView('view-upload-prescription')">
            <i class="fa-solid fa-file-arrow-up"></i> Upload Rx Here
          </button>
          <button class="btn btn-secondary" onclick="switchView('view-medicine-cart')">
            <i class="fa-solid fa-bag-shopping"></i> Order Medicines Directly
          </button>
        </div>
      </div>
    `;
  });
}

function filterPharmacies() {
  const query = document.getElementById('pharmacy-search').value.toLowerCase();
  const filtered = pharmaciesData.filter(p => p.name.toLowerCase().includes(query));
  renderPharmacies(filtered);
}

// Upload Prescription Handler
function triggerFileInput() {
  document.getElementById('rx-file-input').click();
}

function handleRxFileSelect(event) {
  const file = event.target.files[0];
  if (file) {
    state.uploadedRxFile = file;
    document.getElementById('rx-file-name').innerText = file.name;
    document.getElementById('rx-file-size').innerText = `${(file.size / 1024 / 1024).toFixed(1)} MB • Uploaded`;
    document.getElementById('rx-preview-box').style.display = 'flex';
  }
}

function removeRxFile() {
  state.uploadedRxFile = null;
  document.getElementById('rx-preview-box').style.display = 'none';
}

function simulateAIVerification() {
  showToast('AI OCR Scanning Doctor Signature & Medicines...', 'info');
  setTimeout(() => {
    switchView('view-prescription-verification');
  }, 800);
}

// Medicine Cart Management
function updateCartUI() {
  const container = document.getElementById('cart-items-container');
  if (!container) return;
  container.innerHTML = '';
  let subtotal = 0;

  state.cart.forEach(item => {
    subtotal += item.price * item.qty;
    container.innerHTML += `
      <div class="cart-item">
        <div>
          <h4>${item.name}</h4>
          <span style="font-size: 0.8rem; color: var(--text-muted);">${item.rx ? 'Prescription Required' : 'Over The Counter'}</span>
          <p style="font-weight: 600; color: var(--primary);">₹${item.price} x ${item.qty}</p>
        </div>
        <div class="quantity-control">
          <button class="qty-btn" onclick="changeQty(${item.id}, -1)">-</button>
          <span>${item.qty}</span>
          <button class="qty-btn" onclick="changeQty(${item.id}, 1)">+</button>
        </div>
      </div>
    `;
  });

  const delivery = 25;
  const gst = subtotal * 0.12;
  const total = subtotal + delivery + gst;

  document.getElementById('cart-subtotal').innerText = `₹${subtotal.toFixed(2)}`;
  document.getElementById('cart-gst').innerText = `₹${gst.toFixed(2)}`;
  document.getElementById('cart-total').innerText = `₹${total.toFixed(2)}`;
  document.getElementById('cart-badge-count').innerText = state.cart.length;
}

function changeQty(id, delta) {
  const item = state.cart.find(i => i.id === id);
  if (item) {
    item.qty += delta;
    if (item.qty <= 0) {
      state.cart = state.cart.filter(i => i.id !== id);
    }
    updateCartUI();
  }
}

function addToCart(name, price) {
  state.cart.push({ id: Date.now(), name, price, qty: 1, rx: true });
  updateCartUI();
  showToast(`Added ${name} to medicine cart!`, 'success');
}

function applyCoupon() {
  const code = document.getElementById('coupon-code').value.toUpperCase();
  if (code === 'SWASTYA50') {
    showToast('Promo Code SWASTYA50 Applied! 20% Discount Given.', 'success');
  } else {
    showToast('Invalid Coupon Code', 'danger');
  }
}

function processCheckout() {
  showToast('Order Placed Successfully! Dispatching Rider...', 'success');
  setTimeout(() => {
    switchView('view-delivery-tracking');
  }, 1000);
}

// Emergency SOS Modal
function openSOSModal() {
  document.getElementById('sos-modal').classList.add('active');
}
function closeSOSModal() {
  document.getElementById('sos-modal').classList.remove('active');
}
function triggerSOSDispatch() {
  closeSOSModal();
  showToast('EMERGENCY SOS BROADCAST SENT TO 108 AMBULANCE NETWORK!', 'danger');
  switchView('view-ambulance-module');
}

// Auth Modal Controls
function openAuthModal(mode) {
  document.getElementById('auth-modal').classList.add('active');
}
function closeAuthModal() {
  document.getElementById('auth-modal').classList.remove('active');
}
function handleAuthSubmit(e) {
  e.preventDefault();
  closeAuthModal();
  showToast('Signed in successfully!', 'success');
  switchView('view-dashboard');
}

// Toast Notifications System
function showToast(message, type = 'info') {
  const box = document.getElementById('toast-box');
  if (!box) return;
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<i class="fa-solid fa-circle-info"></i> <span>${message}</span>`;
  box.appendChild(toast);
  setTimeout(() => {
    toast.remove();
  }, 4000);
}
