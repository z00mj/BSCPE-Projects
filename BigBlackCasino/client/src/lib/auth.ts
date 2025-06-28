import { apiRequest } from "./queryClient";

export interface User {
  id: number;
  username: string;
  email: string;
  balance: string;
  bbcTokens: string;
}

export interface Admin {
  id: number;
  username: string;
}

export async function login(username: string, password: string): Promise<User> {
  const response = await apiRequest('POST', '/api/auth/login', { username, password });
  const data = await response.json();
  return data.user;
}

export async function register(username: string, email: string, password: string): Promise<User> {
  const response = await apiRequest('POST', '/api/auth/register', { username, email, password });
  const data = await response.json();
  return data.user;
}

export async function logout(): Promise<void> {
  await apiRequest('POST', '/api/auth/logout');
}

export async function getCurrentUser(): Promise<User | null> {
  try {
    const response = await apiRequest('GET', '/api/auth/me');
    const data = await response.json();
    return data.user;
  } catch (error) {
    return null;
  }
}

export async function adminLogin(username: string, password: string): Promise<Admin> {
  const response = await apiRequest('POST', '/api/admin/login', { username, password });
  const data = await response.json();
  return data.admin;
}

export async function adminLogout(): Promise<void> {
  await apiRequest('POST', '/api/admin/logout');
}
