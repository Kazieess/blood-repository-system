// Fictional fixtures only. Statuses are recorded examples, never medical calculations.
export const bloodTypes = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];
export const stock = [
  { type: 'O+', units: 24 }, { type: 'O-', units: 3 },
  { type: 'A+', units: 18 }, { type: 'A-', units: 4 },
  { type: 'B+', units: 15 }, { type: 'B-', units: 2 },
  { type: 'AB+', units: 8 }, { type: 'AB-', units: 1 },
];
export const donors = [
  { id: 'DDS-0001', name: 'Elena Mercado', blood: 'O+', area: 'Digos City', status: 'Eligible', lastDonation: '2026-07-18', donations: 8, registered: '2024-02-12' },
  { id: 'DDS-0002', name: 'Gabriel Santos', blood: 'A+', area: 'Santa Cruz', status: 'For review', lastDonation: '2026-09-08', donations: 3, registered: '2025-05-20' },
  { id: 'DDS-0003', name: 'Isabel Villanueva', blood: 'B+', area: 'Bansalan', status: 'Eligible', lastDonation: '2026-06-14', donations: 5, registered: '2024-08-09' },
  { id: 'DDS-0004', name: 'Miguel Navarro', blood: 'AB+', area: 'Digos City', status: 'Deferred', lastDonation: '2026-08-22', donations: 2, registered: '2025-01-15' },
  { id: 'DDS-0005', name: 'Sofia Reyes', blood: 'O-', area: 'Hagonoy', status: 'Eligible', lastDonation: '2026-07-02', donations: 6, registered: '2023-11-07' },
  { id: 'DDS-0006', name: 'Rafael Bautista', blood: 'A-', area: 'Padada', status: 'For review', lastDonation: null, donations: 0, registered: '2026-11-05' },
  { id: 'DDS-0007', name: 'Camila Flores', blood: 'B-', area: 'Santa Cruz', status: 'Eligible', lastDonation: '2026-05-10', donations: 4, registered: '2024-03-21' },
  { id: 'DDS-0008', name: 'Andres Castillo', blood: 'O+', area: 'Matanao', status: 'Deferred', lastDonation: '2026-09-17', donations: 7, registered: '2023-06-12' },
  { id: 'DDS-0009', name: 'Lucia Ramos', blood: 'AB-', area: 'Digos City', status: 'Eligible', lastDonation: '2026-06-23', donations: 2, registered: '2025-09-02' },
  { id: 'DDS-0010', name: 'Tomas Garcia', blood: 'A+', area: 'Bansalan', status: 'For review', lastDonation: null, donations: 0, registered: '2026-11-04' },
];
export const history = [
  { date: '2026-07-18', reference: 'DON-DEMO-008', location: 'Digos City chapter', type: 'Whole blood' },
  { date: '2026-03-14', reference: 'DON-DEMO-007', location: 'Community donation drive', type: 'Whole blood' },
  { date: '2025-11-08', reference: 'DON-DEMO-006', location: 'Digos City chapter', type: 'Whole blood' },
];
