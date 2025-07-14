const express = require('express');
const path = require('path');
const app = express();
const PORT = 3000;

const admin = require('firebase-admin');
const serviceAccount = require('./firebase-key.json');

admin.initializeApp({
  credential: admin.credential.cert(serviceAccount),
});

const firestore = admin.firestore();
firestore.collection('testConnection').doc('ping').set({ connected: true })
  .then(() => {
    console.log('Connected to Firestore and wrote data successfully.');
  })
  .catch((error) => {
    console.error('Failed to connect to Firestore:', error);
  });

// Serve static files (optional, like CSS, JS, images)
app.use(express.static(path.join(__dirname, 'public')));

// Route for the login page
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'views', 'login.html'));
});

// Start the server
app.listen(PORT, () => {
  console.log(`Server is running at http://localhost:${PORT}`);
});