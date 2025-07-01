import { useNavigate } from "react-router-dom";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { AlertTriangle } from "lucide-react";

interface BannedOverlayProps {
  reason?: string;
}

const BannedOverlay = ({ reason = "Your account has been banned. You can appeal to the admin." }: BannedOverlayProps) => {
  const navigate = useNavigate();

  return (
    <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <Card className="w-full max-w-md bg-red-900/20 border-red-500/50">
        <CardHeader className="text-center">
          <div className="mx-auto w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center mb-4">
            <AlertTriangle className="w-6 h-6 text-red-400" />
          </div>
          <CardTitle className="text-red-400">Account Banned</CardTitle>
        </CardHeader>
        <CardContent className="text-center space-y-4">
          <p className="text-muted-foreground">{reason}</p>
          <Button 
            onClick={() => navigate("/appeal")}
            className="w-full bg-red-600 hover:bg-red-700"
          >
            Appeal Ban
          </Button>
        </CardContent>
      </Card>
    </div>
  );
};

export default BannedOverlay;