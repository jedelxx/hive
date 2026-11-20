import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";

import Login from "./pages/auth/Login";
import SeekerSignup from "./pages/auth/SeekerSignup";
import SeekerDashboard from "./pages/seeker/SeekerDashboard";
import BrowseHives from "./pages/seeker/BrowseHives";
import FindABeestie from "./pages/seeker/FindABeestie";
import SeekerChat from "./pages/seeker/SeekerChat";
import SeekerLayout from "./layouts/SeekerLayout";

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Login />} />
        <Route path="/signup" element={<SeekerSignup />} />

        <Route path="/seeker" element={<SeekerLayout />}>
          <Route index element={<SeekerDashboard />} />
          <Route path="browse-hives" element={<BrowseHives />} />
          <Route path="find-roommate" element={<FindABeestie />} />
          <Route path="shared-chat" element={<SeekerChat />} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;
