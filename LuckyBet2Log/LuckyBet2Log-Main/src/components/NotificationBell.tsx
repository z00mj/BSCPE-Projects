import { useState } from "react";
import { Bell, X, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { useWithdrawalNotifications } from "@/hooks/useWithdrawalNotifications";
import { useDepositNotifications } from "@/hooks/useDepositNotifications";
import { useAuth } from "@/hooks/useAuth";
import { useProfile } from "@/hooks/useProfile";
import { formatDistanceToNow } from "date-fns";

const NotificationBell = () => {
  const [isOpen, setIsOpen] = useState(false);
  const { user } = useAuth();
  const { profile } = useProfile();
  const { 
    notifications: withdrawalNotifications, 
    unreadCount: withdrawalUnreadCount, 
    markAsRead: markWithdrawalAsRead 
  } = useWithdrawalNotifications();

  const { 
    notifications: depositNotifications, 
    unreadCount: depositUnreadCount, 
    markAsRead: markDepositAsRead 
  } = useDepositNotifications();

  // Combine both notification types and sort by date
  const allNotifications = [
    ...withdrawalNotifications.map(n => ({ ...n, type: 'withdrawal' })),
    ...depositNotifications.map(n => ({ ...n, type: 'deposit' }))
  ].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());

  const totalUnreadCount = withdrawalUnreadCount + depositUnreadCount;

  const handleMarkAsRead = async (notificationId: string, type: string) => {
    try {
      if (type === 'withdrawal') {
        await markWithdrawalAsRead.mutateAsync(notificationId);
      } else {
        await markDepositAsRead.mutateAsync(notificationId);
      }
    } catch (error) {
      console.error('Error marking notification as read:', error);
    }
  };

  const handleMarkAllAsRead = async () => {
    try {
      const unreadWithdrawals = withdrawalNotifications.filter(n => !n.is_read);
      const unreadDeposits = depositNotifications.filter(n => !n.is_read);

      for (const notification of unreadWithdrawals) {
        await markWithdrawalAsRead.mutateAsync(notification.id);
      }

      for (const notification of unreadDeposits) {
        await markDepositAsRead.mutateAsync(notification.id);
      }
    } catch (error) {
      console.error('Error marking all notifications as read:', error);
    }
  };

  // Helper function to determine notification type display
  const getNotificationTypeDisplay = (notification: any) => {
    const isAdmin = profile?.is_admin;

    if (isAdmin) {
      // For admins, check if the message contains request language
      if (notification.message.includes('has requested') || notification.message.includes('has submitted')) {
        return notification.type === 'deposit' ? 'Deposit Request' : 'Withdrawal Request';
      }
    }

    // For regular users or admin approval/rejection notifications
    return notification.type === 'deposit' ? 'Deposit' : 'Withdrawal';
  };

  const getNotificationColor = (notification: any) => {
    const isAdmin = profile?.is_admin;

    if (isAdmin && (notification.message.includes('has requested') || notification.message.includes('has submitted'))) {
      // Admin notifications for new requests
      return notification.type === 'deposit' 
        ? 'bg-orange-500/10 text-orange-400 border-orange-500/30' 
        : 'bg-purple-500/10 text-purple-400 border-purple-500/30';
    }

    // Regular user notifications
    return notification.type === 'deposit' 
      ? 'bg-green-500/10 text-green-400 border-green-500/30' 
      : 'bg-blue-500/10 text-blue-400 border-blue-500/30';
  };

  return (
    <Popover open={isOpen} onOpenChange={setIsOpen}>
      <PopoverTrigger asChild>
        <Button variant="ghost" size="sm" className="relative">
          <Bell className="w-4 h-4" />
          {totalUnreadCount > 0 && (
            <Badge 
              variant="destructive" 
              className="absolute -top-1 -right-1 h-5 w-5 rounded-full p-0 flex items-center justify-center text-xs"
            >
              {totalUnreadCount > 99 ? "99+" : totalUnreadCount}
            </Badge>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-80 p-0 z-[150]" align="end">
        <div className="border-b p-4">
          <div className="flex items-center justify-between">
            <h4 className="font-semibold">Notifications</h4>
            {totalUnreadCount > 0 && (
              <Button
                variant="ghost"
                size="sm"
                onClick={handleMarkAllAsRead}
                className="text-xs"
              >
                Mark all as read
              </Button>
            )}
          </div>
        </div>
        <ScrollArea className="h-80">
          {allNotifications.length === 0 ? (
            <div className="p-4 text-center text-muted-foreground">
              <Bell className="w-8 h-8 mx-auto mb-2 opacity-50" />
              <p className="text-sm">No notifications yet</p>
            </div>
          ) : (
            <div className="p-2">
              {allNotifications.map((notification) => (
                <div
                  key={`${notification.type}-${notification.id}`}
                  className={`p-3 rounded-lg mb-2 transition-colors ${
                    notification.is_read 
                      ? "bg-muted/30" 
                      : "bg-primary/10 border border-primary/20"
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex-1 space-y-1">
                      <div className="flex items-center gap-2">
                        <Badge 
                          variant="outline" 
                          className={`text-xs ${getNotificationColor(notification)}`}
                        >
                          {getNotificationTypeDisplay(notification)}
                        </Badge>
                      </div>
                      <p className={`text-sm ${
                        notification.is_read ? "text-muted-foreground" : "text-foreground"
                      }`}>
                        {notification.message}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
                      </p>
                    </div>
                    {!notification.is_read && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleMarkAsRead(notification.id, notification.type)}
                        className="h-6 w-6 p-0"
                      >
                        <Check className="w-3 h-3" />
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </ScrollArea>
      </PopoverContent>
    </Popover>
  );
};

export default NotificationBell;
