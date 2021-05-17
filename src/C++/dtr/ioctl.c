#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>
#include <errno.h>
#include <termios.h>
#include <sys/types.h>
#include <sys/time.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <stdbool.h>
#include <sys/ioctl.h>
#include <signal.h>
#include <string.h>

int main(int argc, char* argv[])
{
  
  if ( argc == 3)
  {
    int fd;
    fd = open("/dev/ttyUSB0",O_RDWR | O_NOCTTY );
    
    int DTR_flag;
        DTR_flag = TIOCM_DTR;

    if ( strcmp(argv[2], "set") == 0 )
    {
      ioctl(fd,TIOCMBIS,&DTR_flag);
      printf("DTR set");
    } 
    
    if ( strcmp(argv[2], "clear") == 0 ) 
    {
      ioctl(fd,TIOCMBIC,&DTR_flag);
      printf("DTR cleared");
    };
    
    close(fd);
      
  } else {
    
    printf("Not enough arguments, port name and DTR (set|clear) requried");
  }
}
