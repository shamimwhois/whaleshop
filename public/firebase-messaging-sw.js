importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-messaging.js');
let config = {
        apiKey: "AIzaSyCnfhH_4U7BgXO2B-kA8Rkqsph0a708rno",
        authDomain: "testingapp-76286.firebaseapp.com",
        projectId: "testingapp-76286",
        storageBucket: "testingapp-76286.firebasestorage.app",
        messagingSenderId: "889245367533",
        appId: "1:889245367533:web:b3074ef47bfe497a7820e4",
        measurementId: "G-G11QET0QDG",
 };
firebase.initializeApp(config);
const messaging = firebase.messaging();
messaging.onBackgroundMessage((payload) => {
    const notificationTitle = payload.notification.title;
    const notificationOptions = {
        body: payload.notification.body,
        icon: '/images/required/firebase-logo.png'
    };
    self.registration.showNotification(notificationTitle, notificationOptions);
});
