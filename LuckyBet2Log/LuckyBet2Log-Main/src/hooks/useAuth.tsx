
import { useState, useEffect, createContext, useContext } from 'react';
import { User, Session, AuthError } from '@supabase/supabase-js';
import { supabase } from '@/integrations/supabase/client';

interface AuthContextType {
  user: User | null;
  session: Session | null;
  loading: boolean;
  signIn: (email: string, password: string) => Promise<{ data: { user: User | null; session: Session | null } | null; error: AuthError | null }>;
  signUp: (email: string, password: string, username: string) => Promise<{ data: { user: User | null; session: Session | null } | null; error: AuthError | null }>;
  signOut: () => Promise<{ error: AuthError | null }>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider = ({ children }: { children: React.ReactNode }) => {
  const [user, setUser] = useState<User | null>(null);
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Set up auth state listener
    const { data: { subscription } } = supabase.auth.onAuthStateChange(
      async (event, session) => {
        console.log('Auth state change:', event, session?.user?.id);
        
        if (event === 'SIGNED_OUT' || !session) {
          setSession(null);
          setUser(null);
          setLoading(false);
          return;
        }
        
        if (event === 'SIGNED_IN' && session) {
          setSession(session);
          setUser(session.user);
          setLoading(false);
          return;
        }
        
        setSession(session);
        setUser(session?.user ?? null);
        setLoading(false);
      }
    );

    // Check for existing session
    supabase.auth.getSession().then(({ data: { session } }) => {
      setSession(session);
      setUser(session?.user ?? null);
      setLoading(false);
    });

    return () => subscription.unsubscribe();
  }, []);

  const signIn = async (email: string, password: string) => {
    const { data, error } = await supabase.auth.signInWithPassword({
      email,
      password,
    });
    return { data: data ? { user: data.user, session: data.session } : null, error };
  };

  const signUp = async (email: string, password: string, username: string) => {
    const { data, error } = await supabase.auth.signUp({
      email,
      password,
      options: {
        data: {
          username,
        },
      },
    });
    return { data: data ? { user: data.user, session: data.session } : null, error };
  };

  const signOut = async (): Promise<{ error: AuthError | null }> => {
    try {
      const { error } = await supabase.auth.signOut();
      
      // Always clear the session state, even if there's an error
      setSession(null);
      setUser(null);
      
      // Clear localStorage and sessionStorage
      try {
        localStorage.clear();
        sessionStorage.clear();
        // Also clear any Supabase specific storage
        Object.keys(localStorage).forEach(key => {
          if (key.includes('supabase')) {
            localStorage.removeItem(key);
          }
        });
        // Clear React Query cache
        if (window.location.pathname !== '/auth') {
          setTimeout(() => {
            window.location.href = '/auth';
          }, 100);
        }
      } catch (storageError) {
        console.warn('Could not clear storage:', storageError);
      }
      
      return { error };
    } catch (err) {
      console.error('Sign out error:', err);
      
      // Force clear state even on error
      setSession(null);
      setUser(null);
      
      if (window.location.pathname !== '/auth') {
        setTimeout(() => {
          window.location.href = '/auth';
        }, 100);
      }
      
      return { error: err as AuthError };
    }
  };

  return (
    <AuthContext.Provider value={{
      user,
      session,
      loading,
      signIn,
      signUp,
      signOut,
    }}>
      {children}
    </AuthContext.Provider>
  );
};

// eslint-disable-next-line react-refresh/only-export-components
export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
